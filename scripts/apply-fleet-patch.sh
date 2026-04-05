#!/usr/bin/env bash
set -euo pipefail

# Apply Fleet Patch v1 to Langdock agents based on database/exports/langdock/fleet-instruction-patches.json
#
# - Dedupe by agent_id (search_agent + synthesis_agent share the same agent_id)
# - Default: dry-run
# - Apply: pass --apply
# - Safe mode: append only the Fleet Patch block if marker is missing
# - Endpoint shape is not fully stable across Langdock deployments;
#   we PATCH with {instruction: ...} first and only fall back if needed.
#
# Requirements: jq, curl

PATCHES_PATH="database/exports/langdock/fleet-instruction-patches.json"
APPLY="false"
ONLY_CONFIG_KEY=""

# Defaults per current app config
GET_URL_DEFAULT="https://api.langdock.com/agent/v1/get"
UPDATE_URL_DEFAULT="https://api.langdock.com/agent/v1/update"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/apply-fleet-patch.sh [--patches PATH] [--only-config-key KEY] [--apply]

Behavior:
  - Dry-run by default (no PATCH requests).
  - For each unique agent_id: GET current instruction, skip if marker already present.
  - Otherwise append the Fleet Patch block extracted from patched_instruction.

Options:
  --patches PATH         Path to fleet-instruction-patches.json (default: database/exports/langdock/fleet-instruction-patches.json)
  --only-config-key KEY  Only process patches with this config_key (repeat not supported)
  --apply                Actually PATCH via update endpoint
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --patches) PATCHES_PATH="${2:-}"; shift 2;;
    --only-config-key) ONLY_CONFIG_KEY="${2:-}"; shift 2;;
    --apply) APPLY="true"; shift 1;;
    -h|--help) usage; exit 0;;
    *) echo "Unknown arg: $1" >&2; usage; exit 1;;
  esac
done

command -v jq >/dev/null 2>&1 || { echo "ERROR: jq not found" >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "ERROR: curl not found" >&2; exit 1; }

if [[ ! -f "$PATCHES_PATH" ]]; then
  echo "ERROR: patches file not found: $PATCHES_PATH" >&2
  exit 1
fi

read_dotenv_var() {
  local key="$1"
  local file=".env"
  [[ -f "$file" ]] || return 1

  local line value
  line="$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$file" | tail -n 1 || true)"
  [[ -z "$line" ]] && return 1

  value="${line#*=}"
  value="$(printf '%s' "$value" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"

  if [[ "$value" =~ ^\".*\"$ ]] && [[ ${#value} -ge 2 ]]; then
    value="${value:1:${#value}-2}"
  elif [[ "$value" =~ ^\'.*\'$ ]] && [[ ${#value} -ge 2 ]]; then
    value="${value:1:${#value}-2}"
  fi

  printf '%s' "$value"
}

LANGDOCK_API_KEY="${LANGDOCK_API_KEY:-$(read_dotenv_var "LANGDOCK_API_KEY" || true)}"
LANGDOCK_GET_URL="${LANGDOCK_GET_URL:-$(read_dotenv_var "LANGDOCK_GET_URL" || true)}"
LANGDOCK_UPDATE_URL="${LANGDOCK_UPDATE_URL:-$(read_dotenv_var "LANGDOCK_UPDATE_URL" || true)}"

: "${LANGDOCK_GET_URL:=$GET_URL_DEFAULT}"
: "${LANGDOCK_UPDATE_URL:=$UPDATE_URL_DEFAULT}"

AUTH_KEY="$LANGDOCK_API_KEY"

if [[ -z "$AUTH_KEY" ]]; then
  echo "ERROR: LANGDOCK_API_KEY not set (env or .env)" >&2
  exit 1
fi

MARKER_BEGIN="$(jq -r '.marker // empty' "$PATCHES_PATH")"
if [[ -z "$MARKER_BEGIN" ]]; then
  echo "ERROR: marker missing in patches JSON" >&2
  exit 1
fi

MARKER_END='=== /APP.LINN.GAMES — FLEET PATCH v1 ==='
LEGACY_MARKER_BEGIN='=== APP: JSON ENVELOPE v1 (DO NOT REMOVE) ==='

strip_wrapper_quotes_if_present() {
  local s="$1"
  if [[ ${#s} -ge 2 && "${s:0:1}" == '"' && "${s: -1}" == '"' ]]; then
    printf '%s' "${s:1:${#s}-2}"
  else
    printf '%s' "$s"
  fi
}

extract_patch_block() {
  local full="$1"
  awk -v begin="$MARKER_BEGIN" -v end="$MARKER_END" '
    $0 == begin {p=1}
    p {print}
    $0 == end {exit}
  ' <<<"$full"
}

do_get_instruction() {
  local agent_id="$1"
  curl -sS \
    -H "Authorization: Bearer ${AUTH_KEY}" \
    --get \
    --data-urlencode "agentId=${agent_id}" \
    "${LANGDOCK_GET_URL}" \
    | jq -r '.agent.instruction // .instruction // ""'
}

# PATCH helper: try {instruction: ...} then fallback to {instructions: ...}
do_patch_instruction() {
  local agent_id="$1"
  local new_instruction="$2"

  if [[ "$APPLY" != "true" ]]; then
    echo "DRY-RUN: would PATCH agent_id=$agent_id (bytes=$(printf '%s' "$new_instruction" | wc -c))"
    return 0
  fi

  local payload http tmp
  tmp="$(mktemp)"

  payload="$(jq -n --arg agentId "$agent_id" --arg instruction "$new_instruction" '{agentId:$agentId, instruction:$instruction}')"
  http="$(curl -sS -o "$tmp" -w '%{http_code}' \
    -X PATCH \
    -H "Authorization: Bearer ${AUTH_KEY}" \
    -H "Content-Type: application/json" \
    --data "$payload" \
    "${LANGDOCK_UPDATE_URL}" || true)"

  if [[ "$http" =~ ^2 ]] && jq -e --arg m "$MARKER_BEGIN" '(.agent.instruction // "") | contains($m)' "$tmp" >/dev/null 2>&1; then
    rm -f "$tmp"
    echo "PATCHED: agent_id=$agent_id (HTTP $http, key=instruction)"
    return 0
  fi

  # Fallback to plural key (some deployments use this key name)
  payload="$(jq -n --arg agentId "$agent_id" --arg instructions "$new_instruction" '{agentId:$agentId, instructions:$instructions}')"
  http="$(curl -sS -o "$tmp" -w '%{http_code}' \
    -X PATCH \
    -H "Authorization: Bearer ${AUTH_KEY}" \
    -H "Content-Type: application/json" \
    --data "$payload" \
    "${LANGDOCK_UPDATE_URL}" || true)"

  if [[ "$http" =~ ^2 ]] && jq -e --arg m "$MARKER_BEGIN" '(.agent.instruction // "") | contains($m)' "$tmp" >/dev/null 2>&1; then
    rm -f "$tmp"
    echo "PATCHED: agent_id=$agent_id (HTTP $http, key=instructions)"
    return 0
  fi

  echo "ERROR: PATCH failed agent_id=$agent_id (HTTP $http)" >&2
  cat "$tmp" >&2 || true
  rm -f "$tmp"
  return 1
}

# Dedupe patches by agent_id; keep first patched_instruction, track involved config_keys.
DEDUPED_STREAM="$(jq -c '
  .patches
  | (if type=="array" then . else [] end)
  | (if $only != "" then map(select(.config_key == $only)) else . end)
  | group_by(.agent_id)
  | map({
      agent_id: .[0].agent_id,
      config_keys: (map(.config_key) | unique),
      agent_names: (map(.agent_name) | unique),
      patched_instruction: .[0].patched_instruction,
      count: length,
      inconsistent: ((map(.patched_instruction) | unique | length) > 1)
    })
  | .[]
' --arg only "$ONLY_CONFIG_KEY" "$PATCHES_PATH")"

if [[ -z "$DEDUPED_STREAM" ]]; then
  echo "Nothing to do (no matching patches)." >&2
  exit 0
fi

total=0
patched=0
skipped=0
failed=0

while IFS= read -r row; do
  [[ -z "$row" ]] && continue
  total=$((total+1))

  agent_id="$(jq -r '.agent_id' <<<"$row")"
  inconsistent="$(jq -r '.inconsistent' <<<"$row")"
  config_keys="$(jq -r '.config_keys | join(",")' <<<"$row")"
  agent_names="$(jq -r '.agent_names | join(" / ")' <<<"$row")"

  if [[ "$inconsistent" == "true" ]]; then
    echo "WARN: agent_id=$agent_id has multiple different patched_instruction values; using the first." >&2
  fi

  patched_instruction_raw="$(jq -r '.patched_instruction' <<<"$row")"
  patched_instruction="$(strip_wrapper_quotes_if_present "$patched_instruction_raw")"

  echo "TARGET: agent_id=$agent_id config_keys=[$config_keys] name=[$agent_names]"

  current="$(do_get_instruction "$agent_id")"
  if [[ -z "$current" ]]; then
    echo "WARN: GET returned empty instruction; skipping agent_id=$agent_id" >&2
    skipped=$((skipped+1))
    continue
  fi

  if grep -Fq "$MARKER_BEGIN" <<<"$current" || grep -Fq "$LEGACY_MARKER_BEGIN" <<<"$current"; then
    echo "OK (no change): marker already present agent_id=$agent_id"
    skipped=$((skipped+1))
    continue
  fi

  block="$(extract_patch_block "$patched_instruction")"
  if [[ -z "$block" ]]; then
    echo "ERROR: could not extract patch block for agent_id=$agent_id" >&2
    failed=$((failed+1))
    continue
  fi

  new_instruction="$(printf '%s\n\n%s\n' "$(printf '%s' "$current" | sed -E 's/[[:space:]]+$//')" "$block")"

  if do_patch_instruction "$agent_id" "$new_instruction"; then
    patched=$((patched+1))
  else
    failed=$((failed+1))
  fi

done <<<"$DEDUPED_STREAM"

echo ""
echo "Summary:"
echo "  unique_agent_ids: $total"
echo "  patched:         $patched"
echo "  skipped:         $skipped"
echo "  failed:          $failed"
echo "  apply:           $APPLY"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi

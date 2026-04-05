#!/usr/bin/env bash
set -euo pipefail

# PATCH per curl mit NUR {agentId, instruction}
# Quelle: database/exports/langdock/fleet-instruction-patches.json
#
# Requirements: jq, curl
# Env:
#   LANGDOCK_API_KEY (required)
# Optional:
#   LANGDOCK_UPDATE_URL (default: https://api.langdock.com/agent/v1/update)

PATCHES_PATH="database/exports/langdock/fleet-instruction-patches.json"
UPDATE_URL="${LANGDOCK_UPDATE_URL:-https://api.langdock.com/agent/v1/update}"

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

if [[ -z "$LANGDOCK_API_KEY" ]]; then
  echo "ERROR: Missing LANGDOCK_API_KEY (env or .env)." >&2
  exit 1
fi

# Dedupe by agent_id (keep first patched_instruction)
# NOTE: patched_instruction values in this file are wrapped in outer quotes; strip them.
DEDUPED="$(jq -c '
  .patches
  | group_by(.agent_id)
  | map({agent_id: .[0].agent_id, patched_instruction: .[0].patched_instruction, config_keys: (map(.config_key)|unique)})
  | .[]
' "$PATCHES_PATH")"

if [[ -z "$DEDUPED" ]]; then
  echo "Nothing to patch." >&2
  exit 0
fi

patched=0
failed=0

while IFS= read -r row; do
  [[ -z "$row" ]] && continue

  agent_id="$(jq -r '.agent_id' <<<"$row")"
  config_keys="$(jq -r '.config_keys | join(",")' <<<"$row")"
  instructions_raw="$(jq -r '.patched_instruction' <<<"$row")"

  # Strip one leading/trailing quote if present
  if [[ ${#instructions_raw} -ge 2 && "${instructions_raw:0:1}" == '"' && "${instructions_raw: -1}" == '"' ]]; then
    instructions="${instructions_raw:1:${#instructions_raw}-2}"
  else
    instructions="$instructions_raw"
  fi

  payload="$(jq -n --arg agentId "$agent_id" --arg instruction "$instructions" '{agentId:$agentId, instruction:$instruction}')"

  echo "PATCH agent_id=$agent_id config_keys=[$config_keys]"

  http="$(curl -sS -o /tmp/langdock_patch_resp.json -w '%{http_code}' \
    -X PATCH \
    -H "Authorization: Bearer ${LANGDOCK_API_KEY}" \
    -H "Content-Type: application/json" \
    "$UPDATE_URL" \
    -d "$payload" || true)"

  # Some deployments return HTTP 200 with an error message.
  if jq -e '.message? // empty | test("invalid|not found|does not have|forbidden"; "i")' /tmp/langdock_patch_resp.json >/dev/null 2>&1; then
    failed=$((failed+1))
    echo "FAIL (body error) HTTP $http" >&2
    cat /tmp/langdock_patch_resp.json >&2 || true
  elif [[ "$http" =~ ^2 ]]; then
    patched=$((patched+1))
    echo "OK HTTP $http"
  else
    failed=$((failed+1))
    echo "FAIL HTTP $http" >&2
    cat /tmp/langdock_patch_resp.json >&2 || true
  fi

done <<<"$DEDUPED"

echo ""
echo "Summary: patched=$patched failed=$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi

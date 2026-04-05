#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$repo_root/.env"

if [[ ! -f "$env_file" ]]; then
  echo "ERROR: .env not found at: $env_file" >&2
  exit 1
fi

read_dotenv_var() {
  local key="$1"

  # Reads the *last* matching assignment to mimic dotenv override behavior.
  # Does NOT eval or source the file.
  local line
  line="$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$env_file" | tail -n 1 || true)"

  if [[ -z "$line" ]]; then
    return 1
  fi

  local value
  value="${line#*=}"
  value="$(printf '%s' "$value" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"

  # Strip surrounding single/double quotes if present
  if [[ "$value" =~ ^\".*\"$ ]] && [[ ${#value} -ge 2 ]]; then
    value="${value:1:${#value}-2}"
  elif [[ "$value" =~ ^\'.*\'$ ]] && [[ ${#value} -ge 2 ]]; then
    value="${value:1:${#value}-2}"
  fi

  printf '%s' "$value"
}

langdock_api_key="$(read_dotenv_var "LANGDOCK_API_KEY" || true)"
if [[ -z "${langdock_api_key}" ]]; then
  echo "ERROR: LANGDOCK_API_KEY is missing/empty in .env" >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: docker not found in PATH" >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: 'docker compose' not available (need Docker Compose v2)" >&2
  exit 1
fi

service="php-cli"
if [[ -z "$(docker compose ps -q "$service" || true)" ]]; then
  echo "ERROR: Compose service '$service' is not running." >&2
  echo "Start it first: docker compose up -d" >&2
  exit 1
fi

export_dir_host="$repo_root/database/exports/langdock"
mkdir -p "$export_dir_host"

stamp="$(date +%Y%m%d-%H%M%S)"
export_relpath="database/exports/langdock/langdock-agent-export-${stamp}.json"
export_path_in_container="/var/www/${export_relpath}"

# 1) Clear cached agent list so we always fetch fresh instructions.
docker compose exec -T "$service" php artisan cache:forget langdock.agents.list >/dev/null || true

# 2) Export full agent configs (including instruction/masterprompt).
written_path="$(docker compose exec -T -e EXPORT_PATH="$export_path_in_container" "$service" php -r '
$autoload = __DIR__ . "/vendor/autoload.php";
if (!file_exists($autoload)) {
    fwrite(STDERR, "ERROR: vendor/autoload.php not found in container.\n");
    exit(1);
}

require $autoload;

$app = require __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var App\Services\LangdockAgentService $svc */
$svc = app(App\Services\LangdockAgentService::class);

$configured = $svc->configuredAgents();
$agents = $svc->listAgents();

$agentsById = [];
foreach ($agents as $agent) {
    if (is_array($agent) && isset($agent["id"])) {
        $agentsById[(string) $agent["id"]] = $agent;
    }
}

$items = [];
$orphaned = [];
foreach ($configured as $configKey => $agentId) {
    $agentIdStr = (string) $agentId;
    $agent = $agentsById[$agentIdStr] ?? null;

    if ($agent === null) {
        $orphaned[] = $configKey;
    }

    $items[] = [
        "config_key" => (string) $configKey,
        "agent_id" => $agentIdStr,
        "found" => $agent !== null,
        "name" => is_array($agent) ? ($agent["name"] ?? null) : null,
        "instruction" => is_array($agent) ? ($agent["instruction"] ?? null) : null,
        "agent" => $agent,
    ];
}

$export = [
    "generated_at" => (new DateTimeImmutable("now", new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM),
    "app_env" => (string) (getenv("APP_ENV") ?: ""),
    "app_url" => (string) (getenv("APP_URL") ?: ""),
    "configured_count" => count($configured),
    "fetched_count" => count($agentsById),
    "orphaned_config_keys" => $orphaned,
    "items" => $items,
];

$path = getenv("EXPORT_PATH");
if (!$path) {
    fwrite(STDERR, "ERROR: EXPORT_PATH env var missing.\n");
    exit(1);
}

$dir = dirname($path);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "ERROR: Cannot create export directory: {$dir}\n");
    exit(1);
}

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "ERROR: json_encode failed.\n");
    exit(1);
}

file_put_contents($path, $json . "\n");

fwrite(STDOUT, $path . "\n");
')"

# Normalize to workspace-relative path for convenience.
written_path="${written_path%$'\r'}"

if [[ "$written_path" != "$export_path_in_container" ]]; then
  echo "WARN: Unexpected export path returned: $written_path" >&2
fi

echo "OK: Export written to ${export_relpath}"

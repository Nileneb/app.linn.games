#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────
# deploy.sh — Production build & deploy for app.linn.games
# Usage: ./deploy.sh [--skip-build] [--skip-migrate]
# ──────────────────────────────────────────────

SKIP_BUILD=false
SKIP_MIGRATE=false

for arg in "$@"; do
  case "$arg" in
    --skip-build)   SKIP_BUILD=true ;;
    --skip-migrate) SKIP_MIGRATE=true ;;
    *) echo "Unknown option: $arg"; exit 1 ;;
  esac
done

cd "$(dirname "$0")"

# ── Pre-flight checks ──────────────────────────
if ! command -v docker &>/dev/null; then
  echo "ERROR: docker is not installed." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "ERROR: .env file not found. Copy .env.example and configure it first." >&2
  exit 1
fi

echo "==> Pulling latest images..."
docker compose pull postgres redis postgres-mcp

# ── Build ──────────────────────────────────────
if [ "$SKIP_BUILD" = false ]; then
  echo "==> Building production images..."
  docker compose build --no-cache web php-fpm queue-worker php-cli
else
  echo "==> Skipping build (--skip-build)"
fi

# ── Start infrastructure ───────────────────────
echo "==> Starting postgres & redis..."
docker compose up -d postgres redis
echo "==> Waiting for postgres healthcheck..."
docker compose exec postgres pg_isready -q --timeout=30 || {
  echo "ERROR: Postgres did not become ready." >&2
  exit 1
}

# ── Migrate ────────────────────────────────────
if [ "$SKIP_MIGRATE" = false ]; then
  echo "==> Running database migrations..."
  docker compose run --rm php-cli php artisan migrate --force
else
  echo "==> Skipping migrations (--skip-migrate)"
fi

# ── Cache optimisation ─────────────────────────
echo "==> Caching config, routes & views..."
docker compose run --rm php-cli php artisan config:cache
docker compose run --rm php-cli php artisan route:cache
docker compose run --rm php-cli php artisan view:cache

# ── Start all services ─────────────────────────
echo "==> Clearing stale build-asset volume..."
docker compose down --remove-orphans 2>/dev/null || true
docker volume rm applinngames_linn-build-assets 2>/dev/null || true

echo "==> Starting all services..."
docker compose up -d

# ── Verify ─────────────────────────────────────
echo "==> Waiting for services to settle..."
sleep 5

echo "==> Service status:"
docker compose ps

# ── Deploy notification mail ───────────────────
echo "==> Sending deploy notification..."
docker compose run --rm php-cli php artisan deploy:notify || echo "WARN: Deploy notification mail failed."

echo ""
echo "==> Deployment complete."
echo "    App:          http://localhost:6479"
echo "    Postgres MCP: http://localhost:8200/sse"

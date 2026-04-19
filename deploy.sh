#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

DC=(docker compose)

# ── Pre-flight ─────────────────────────────────
if ! command -v docker &>/dev/null; then
  echo "ERROR: docker is not installed." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "ERROR: .env file not found." >&2
  exit 1
fi

# ── Shared infrastructure ──────────────────────
echo "==> Ensuring linn-shared network exists..."
docker network inspect linn-shared &>/dev/null || docker network create linn-shared

echo "==> Ensuring linn-papers-data volume exists..."
docker volume inspect linn-papers-data &>/dev/null || docker volume create linn-papers-data

echo "==> Pulling latest images from Docker Hub..."
"${DC[@]}" pull postgres web php-fpm queue-worker php-cli mcp-paper-search redis

# ── Start infrastructure ───────────────────────
echo "==> Cleaning up stale containers..."
"${DC[@]}" down --remove-orphans 2>/dev/null || true
# Remove any ghost containers with hash-prefixed names that block compose
docker ps -aq --filter "name=applinngames-" | xargs -r docker rm -f 2>/dev/null || true

echo "==> Starting postgres & redis..."
"${DC[@]}" up -d postgres redis
echo "==> Waiting for postgres..."
for i in $(seq 1 12); do
  if "${DC[@]}" exec -T postgres pg_isready -q 2>/dev/null; then
    echo "    Postgres ready after $((i * 5))s."
    break
  fi
  if [ "$i" -eq 12 ]; then
    echo "ERROR: Postgres did not become ready after 60s." >&2
    "${DC[@]}" logs postgres --tail=20 >&2
    exit 1
  fi
  sleep 5
done

echo "==> Ensuring Postgres extensions..."
"${DC[@]}" exec -T postgres sh -lc \
  'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS hypopg; CREATE EXTENSION IF NOT EXISTS pg_stat_statements;"'

# ── Backup ─────────────────────────────────────
BACKUP_DIR="./backups"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/pre-deploy-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Creating database backup..."
"${DC[@]}" exec -T postgres sh -lc \
  'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-acl' > "$BACKUP_FILE" 2>/dev/null || {
  echo "ERROR: Database backup failed." >&2
  exit 1
}
echo "    Backup saved to $BACKUP_FILE"

# ── Migrate ────────────────────────────────────
echo "==> Running database migrations..."
if ! "${DC[@]}" run --rm php-cli php artisan migrate --force; then
  echo "ERROR: Migration failed. Rolling back from backup..." >&2
  "${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$BACKUP_FILE" || \
    echo "CRITICAL: Rollback also failed! Manual restore needed from: $BACKUP_FILE" >&2
  exit 1
fi

echo "==> Seeding roles..."
if ! "${DC[@]}" run --rm php-cli php artisan db:seed --class=RoleSeeder --force; then
  echo "ERROR: RoleSeeder failed." >&2
  exit 1
fi

# ── Cache ──────────────────────────────────────
echo "==> Rebuilding config/route/view cache..."
"${DC[@]}" run --rm php-cli php artisan optimize:clear
"${DC[@]}" run --rm php-cli php artisan config:cache
"${DC[@]}" run --rm php-cli php artisan route:cache
"${DC[@]}" run --rm php-cli php artisan view:cache

# ── Start all services ─────────────────────────
echo "==> Clearing stale build-asset volume..."
docker volume rm applinngames_linn-build-assets 2>/dev/null || true

echo "==> Starting all services..."
"${DC[@]}" up -d

echo "==> Restarting nginx..."
"${DC[@]}" restart web 2>/dev/null || true

echo "==> Restarting queue workers..."
"${DC[@]}" run --rm php-cli php artisan queue:restart

# ── Health check ───────────────────────────────
echo "==> Health check..."
HEALTH_URL="http://localhost:${APP_PORT:-6481}"
for i in $(seq 1 12); do
  HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$HEALTH_URL" 2>/dev/null); HTTP_STATUS=${HTTP_STATUS:-000}
  if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 500 ]; then
    echo "    HTTP $HTTP_STATUS after $((i * 5))s — OK"
    break
  fi
  if [ "$i" -eq 12 ]; then
    echo "WARN: App did not respond after 60s (last: HTTP $HTTP_STATUS)" >&2
    echo "      Check logs: docker compose logs php-fpm"
  fi
  sleep 5
done

# ── Post-deploy ────────────────────────────────
echo "==> Running post-deploy (admin user + workspace)..."
"${DC[@]}" run --rm php-cli php artisan deploy:post-deploy || echo "WARN: Post-deploy failed."

echo ""
echo "==> Deployment complete."
echo "    App: http://localhost:6479"

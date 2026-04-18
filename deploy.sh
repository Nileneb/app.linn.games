#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────
# deploy.sh — Production build & deploy for app.linn.games
# Usage: ./deploy.sh [--skip-build] [--skip-migrate] [--seed] [--fresh] [--with-mcp]
#                   [--migrate-only]
#
# --migrate-only   Schnell-Deploy: nur Migrate + Cache + Queue-Restart
#                  Ideal für Hotfixes ohne Rebuild (z. B. fehlende Migrationen nachholen)
# ──────────────────────────────────────────────

SKIP_BUILD=false
SKIP_MIGRATE=false
RUN_SEED=false
FRESH_DB=false
WITH_MCP=false
MIGRATE_ONLY=false

for arg in "$@"; do
  case "$arg" in
    --skip-build)    SKIP_BUILD=true ;;
    --skip-migrate)  SKIP_MIGRATE=true ;;
    --seed)          RUN_SEED=true ;;
    --fresh)         FRESH_DB=true ;;
    --with-mcp)      WITH_MCP=true ;;
    --migrate-only)  MIGRATE_ONLY=true ;;
    *) echo "Unknown option: $arg"; exit 1 ;;
  esac
done

cd "$(dirname "$0")"

DC=(docker compose)
if [ "$WITH_MCP" = true ]; then
  DC+=(--profile mcp)
fi

# ── Migrate-only: schneller Hotfix-Pfad ────────
if [ "$MIGRATE_ONLY" = true ]; then
  # Alle PHP-Images neu bauen — Code ist baked in, nicht bind-gemountet.
  # Ohne Rebuild laufen php-fpm und queue-worker mit veraltetem Code.
  echo "==> [migrate-only] Rebuilding PHP images (php-cli, php-fpm, queue-worker)..."
  "${DC[@]}" build php-cli php-fpm queue-worker

  echo "==> [migrate-only] Clearing stale config cache..."
  "${DC[@]}" run --rm php-cli php artisan optimize:clear

  echo "==> [migrate-only] Running database migrations..."
  if ! "${DC[@]}" run --rm php-cli php artisan migrate --force; then
    echo "ERROR: Migration failed." >&2
    exit 1
  fi

  echo "==> [migrate-only] Rebuilding config/route/view cache..."
  "${DC[@]}" run --rm php-cli php artisan config:cache
  "${DC[@]}" run --rm php-cli php artisan route:cache
  "${DC[@]}" run --rm php-cli php artisan view:cache

  echo "==> [migrate-only] Restarting queue workers with new image..."
  "${DC[@]}" run --rm php-cli php artisan queue:restart
  # Explizit neu starten damit das neue Image verwendet wird
  "${DC[@]}" up -d --no-deps php-fpm queue-worker

  echo "==> [migrate-only] Reloading nginx (refresh upstream DNS)..."
  "${DC[@]}" exec -T web nginx -s reload 2>/dev/null || true

  echo ""
  echo "==> Migrate-only deploy complete."
  exit 0
fi

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
"${DC[@]}" pull postgres redis

# ── Frontend: Vite build ────────────────────────
echo "==> Installing npm dependencies..."
if ! npm install --frozen-lockfile; then
  echo "ERROR: npm install failed." >&2
  exit 1
fi

echo "==> Building frontend assets with Vite..."
if ! npm run build; then
  echo "ERROR: Vite build failed." >&2
  exit 1
fi

# ── Build ──────────────────────────────────────
if [ "$SKIP_BUILD" = false ]; then
  echo "==> Building production images..."
  MAYRING_SERVICES="mayring-api mayring-mcp mayring-webui mayring-pi"
  if [ "$WITH_MCP" = true ]; then
    "${DC[@]}" build --no-cache postgres web php-fpm queue-worker php-cli mcp-paper-search $MAYRING_SERVICES
  else
    "${DC[@]}" build --no-cache postgres web php-fpm queue-worker php-cli $MAYRING_SERVICES
  fi
else
  echo "==> Skipping build (--skip-build)"
fi

# ── Start infrastructure ───────────────────────
echo "==> Starting postgres & redis..."
"${DC[@]}" up -d postgres redis
echo "==> Waiting for postgres healthcheck..."
"${DC[@]}" exec postgres pg_isready -q --timeout=30 || {
  echo "ERROR: Postgres did not become ready." >&2
  exit 1
}

echo "==> Ensuring required Postgres extensions..."
"${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS hypopg; CREATE EXTENSION IF NOT EXISTS pg_stat_statements;"'

# ── Backup ─────────────────────────────────────
BACKUP_DIR="./backups"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/pre-deploy-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Creating database backup..."
"${DC[@]}" exec -T postgres sh -lc 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-acl' > "$BACKUP_FILE" 2>/dev/null || {
  echo "ERROR: Database backup failed." >&2
  exit 1
}
echo "    Backup saved to $BACKUP_FILE"

# ── Fresh database (optional) ──────────────────
if [ "$FRESH_DB" = true ]; then
  echo "==> Dropping and recreating database (--fresh)..."
  # Terminate all active connections, then drop — connect to 'postgres' system DB to avoid "cannot drop currently open database"
  "${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d postgres -tc "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '"'"'"$POSTGRES_DB"'"'"' AND pid <> pg_backend_pid();"' || true
  sleep 1
  "${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d postgres -tc "DROP DATABASE IF EXISTS \"$POSTGRES_DB\";"'
  "${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d postgres -tc "CREATE DATABASE \"$POSTGRES_DB\";"'
  echo "    Database reset complete."
  echo "==> Recreating Postgres extensions..."
  "${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS hypopg; CREATE EXTENSION IF NOT EXISTS pg_stat_statements;"'
  # After fresh DB, we must run migrations
  SKIP_MIGRATE=false
fi

# ── Migrate ────────────────────────────────────
if [ "$SKIP_MIGRATE" = false ]; then
  echo "==> Running database migrations..."
  if ! "${DC[@]}" run --rm php-cli php artisan migrate --force; then
    echo "ERROR: Migration failed. Rolling back from backup..." >&2
    "${DC[@]}" exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$BACKUP_FILE" || {
      echo "CRITICAL: Rollback also failed! Manual restore needed from: $BACKUP_FILE" >&2
    }
    exit 1
  fi
  
  # ── Seed roles (always, since it's idempotent via firstOrCreate)
  echo "==> Seeding roles..."
  if ! "${DC[@]}" run --rm php-cli php artisan db:seed --class=RoleSeeder --force; then
    echo "ERROR: RoleSeeder failed." >&2
    exit 1
  fi

  echo "==> Restarting queue workers (neue Migrations-Klassen laden)..."
  "${DC[@]}" run --rm php-cli php artisan queue:restart
else
  echo "==> Skipping migrations (--skip-migrate)"
fi

# ── Seed ───────────────────────────────────────
if [ "$RUN_SEED" = true ]; then
  echo "==> Seeding database (admin user + roles)..."
  "${DC[@]}" run --rm php-cli php artisan db:seed --force
else
  echo "==> Skipping full seed (use --seed for initial deploy)"
fi

# ── Cache optimisation ─────────────────────────
echo "==> Caching config, routes & views..."
"${DC[@]}" run --rm php-cli php artisan config:cache
"${DC[@]}" run --rm php-cli php artisan route:cache
"${DC[@]}" run --rm php-cli php artisan view:cache

# ── Start all services ─────────────────────────
echo "==> Clearing stale build-asset volume..."
"${DC[@]}" down --remove-orphans 2>/dev/null || true
docker volume rm applinngames_linn-build-assets 2>/dev/null || true

echo "==> Starting all services..."
"${DC[@]}" up -d

echo "==> Reloading nginx (refresh upstream DNS after container recreate)..."
"${DC[@]}" exec -T web nginx -s reload 2>/dev/null || true

# ── Verify ─────────────────────────────────────
echo "==> Waiting for services to settle..."
sleep 5

echo "==> Service status:"
"${DC[@]}" ps

# ── Health check ───────────────────────────────
echo "==> Running health check..."
HEALTH_URL="http://localhost:6479"
MAX_RETRIES=12
RETRY_INTERVAL=5
for i in $(seq 1 $MAX_RETRIES); do
  HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$HEALTH_URL" 2>/dev/null || echo "000")
  if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 500 ]; then
    echo "    App responded with HTTP $HTTP_STATUS after $((i * RETRY_INTERVAL))s."
    break
  fi
  if [ "$i" -eq "$MAX_RETRIES" ]; then
    echo "WARN: App did not respond after $((MAX_RETRIES * RETRY_INTERVAL))s (last status: $HTTP_STATUS)." >&2
    echo "      Check logs: ${DC[*]} logs php-fpm"
  fi
  sleep "$RETRY_INTERVAL"
done

# ── Post-deploy: ensure admin + workspace ─────────────────
echo "==> Running post-deploy (admin user + workspace)..."
"${DC[@]}" run --rm php-cli php artisan deploy:post-deploy || echo "WARN: Post-deploy failed."

# Reset-Link nur bei --fresh (DB-Neuaufbau), nicht bei jedem normalen Deploy
if [ "$FRESH_DB" = true ]; then
  echo "==> Sending password reset link (--fresh)..."
  "${DC[@]}" run --rm php-cli php artisan deploy:send-reset-link || echo "WARN: Reset-Link fehlgeschlagen."
fi

echo ""
echo "==> Deployment complete."
echo "    App:          http://localhost:6479"

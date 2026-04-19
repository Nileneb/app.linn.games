#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

DM=(docker compose -f docker-compose.mayring.yml --project-name mayring)

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

echo "==> Ensuring linn-mayring-cache volume exists..."
docker volume inspect linn-mayring-cache &>/dev/null || docker volume create linn-mayring-cache

# ── Pull image from GHCR ──────────────────────
echo "==> Pulling MayringCoder image from GHCR..."
docker pull ghcr.io/nileneb/mayring:latest

# ── Deploy ─────────────────────────────────────
echo "==> Stopping old MayringCoder containers..."
"${DM[@]}" down --remove-orphans 2>/dev/null || true

echo "==> Starting MayringCoder stack..."
"${DM[@]}" up -d

# ── Health check ───────────────────────────────
echo "==> Health check (http://localhost:6480/ui/)..."
HEALTH_URL="http://localhost:6480/ui/"
for i in $(seq 1 12); do
  HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$HEALTH_URL" 2>/dev/null); HTTP_STATUS=${HTTP_STATUS:-000}
  if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 500 ]; then
    echo "    HTTP $HTTP_STATUS after $((i * 5))s — OK"
    break
  fi
  if [ "$i" -eq 12 ]; then
    echo "WARN: MayringCoder did not respond after 60s (last: HTTP $HTTP_STATUS)" >&2
    echo "      Check logs: docker compose -f docker-compose.mayring.yml logs mayring-webui"
  fi
  sleep 5
done

echo ""
echo "==> MayringCoder deploy complete."
echo "    UI:  http://localhost:6480/ui/"
echo "    MCP: http://localhost:6480/"

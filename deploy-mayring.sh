#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

DEPLOY_TAG="${DEPLOY_TAG:-latest}"
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

echo "==> Ensuring linn-papers-data volume exists..."
docker volume inspect linn-papers-data &>/dev/null || docker volume create linn-papers-data

# ── Pull image from Docker Hub ─────────────────
# docker-compose.mayring.yml uses `image: nileneb/mayring:latest`. If a specific
# tag is requested (e.g. git-sha from CI), pull it and locally alias it as :latest
# so compose picks it up without needing a compose file edit.
echo "==> Pulling MayringCoder image (tag=${DEPLOY_TAG}) from Docker Hub..."
docker pull "nileneb/mayring:${DEPLOY_TAG}"
if [ "${DEPLOY_TAG}" != "latest" ]; then
  docker tag "nileneb/mayring:${DEPLOY_TAG}" nileneb/mayring:latest
fi

# ── Deploy ─────────────────────────────────────
echo "==> Stopping old MayringCoder containers..."
# Also remove any standalone watcher container started outside compose (legacy cleanup).
docker stop mayring-watcher 2>/dev/null && docker rm mayring-watcher 2>/dev/null || true
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

#!/usr/bin/env bash
# Lokal builden (auf BigOne) + nach Docker Hub pushen.
# Danach GitHub Actions "Deploy to Production" manuell triggern oder deploy.sh auf u-server.
set -euo pipefail

TAG=${1:-latest}

build_push() {
  local name="$1"
  local image="nileneb/linn-${name}:${TAG}"
  local extra="${2:-}"
  echo ""
  echo "==> Building $image ..."
  eval "docker build -t \"$image\" $extra"
  echo "==> Pushing $image ..."
  docker push "$image"
}

# web (nginx)
build_push "web" \
  "-f ./docker/common/nginx/dockerfile ."

# php (fpm + cli + worker + reverb — Vite baked in via build-args)
build_push "php" \
  "--target production \
   --build-arg VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY:-} \
   --build-arg VITE_REVERB_HOST=${VITE_REVERB_HOST:-app.linn.games} \
   --build-arg VITE_REVERB_PORT=${VITE_REVERB_PORT:-443} \
   --build-arg VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME:-https} \
   -f ./docker/common/php-fpm/dockerfile ."

# postgres (pgvector + hypopg)
build_push "postgres" \
  "-f ./docker/common/postgres/dockerfile ."

# paper-search mcp
build_push "paper-search" \
  "-f ./paper-search-mcp/Dockerfile ./paper-search-mcp"

echo ""
echo "Done: alle Images auf Docker Hub (tag: $TAG)."
echo "Deploy: gh workflow run 'Deploy to Production' --repo Nileneb/app.linn.games"

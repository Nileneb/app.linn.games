#!/bin/bash
set -e

# Sync built frontend assets to the shared volume so nginx can serve them
if [ -d /var/www/public/build ] && [ -d /var/www/public/build-shared ]; then
    echo "Syncing build assets to shared volume..."
    rm -rf /var/www/public/build-shared/*
    cp -a /var/www/public/build/. /var/www/public/build-shared/
fi

# Wait for database to be ready
echo "Waiting for database..."
while ! php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; do
    sleep 2
done
echo "Database is ready!"

# Migrations and cache are handled by deploy.sh — NOT here.
# Running them in the entrypoint caused double execution and DNS failures
# when deploy.sh already orchestrates these steps via `docker compose run --rm php-cli`.

# Resolve env vars in MCP config (Claude CLI doesn't do envsubst)
if [ -f /var/www/.claude/mcp-production.json ] && [ -n "$MCP_AUTH_TOKEN" ]; then
    sed -i "s|\${MCP_AUTH_TOKEN}|$MCP_AUTH_TOKEN|g" /var/www/.claude/mcp-production.json
fi

# Fix ownership after root-executed artisan commands so www-data can write
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Execute the main command (php-fpm or queue:work)
exec "$@"

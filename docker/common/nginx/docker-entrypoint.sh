#!/bin/sh
set -e

# If a template file exists (from volume mount), generate config from it
TEMPLATE="/etc/nginx/templates/default.conf.template"
TARGET="/etc/nginx/conf.d/default.conf"

if [ -f "$TEMPLATE" ]; then
    cp "$TEMPLATE" "$TARGET"
fi

# Replace MCP auth token placeholder in nginx config
if [ -n "$MCP_AUTH_TOKEN" ]; then
    sed -i "s|__MCP_AUTH_TOKEN__|${MCP_AUTH_TOKEN}|g" "$TARGET"
else
    echo "WARNING: MCP_AUTH_TOKEN not set — MCP endpoint will reject all requests"
fi

exec "$@"

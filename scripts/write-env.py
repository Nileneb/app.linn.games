#!/usr/bin/env python3
"""Writes .env from GitHub Environment variables (secrets + variables).
Called by CI/CD deploy workflows. All keys are injected via workflow env: block.

Multiline-Werte (PEM-Keys) werden automatisch base64-encoded, damit die .env
single-line safe bleibt. JwtIssuer/Middleware decoden automatisch.
"""
import base64
import os
from pathlib import Path

env_path = Path(__file__).parent.parent / '.env'

KEYS = [
    # ── Secrets ─────────────────────────────────────────────────────
    'APP_KEY',
    'DB_PASSWORD', 'POSTGRES_PASSWORD',
    'REVERB_APP_SECRET',
    'CLAUDE_API_KEY',
    'MCP_AUTH_TOKEN', 'MAYRING_MCP_AUTH_TOKEN', 'MCP_SERVICE_TOKEN',
    'JWT_PRIVATE_KEY', 'JWT_PUBLIC_KEY',
    'PAPER_SEARCH_TOKEN',
    'STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET',
    'GH_CLIENT_ID', 'GH_CLIENT_SECRET',  # GitHub blocks GITHUB_ prefix — mapped below
    'MAIL_PASSWORD',
    'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
    'LANGDOCK_DB_USERNAME', 'LANGDOCK_DB_PASSWORD',
    # ── Variables ────────────────────────────────────────────────────
    'APP_NAME', 'VITE_APP_NAME', 'APP_ENV', 'APP_URL', 'APP_PORT', 'APP_DEBUG',
    'APP_LOCALE', 'APP_FALLBACK_LOCALE', 'APP_FAKER_LOCALE',
    'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS',
    'LOG_CHANNEL', 'LOG_STACK', 'LOG_DEPRECATIONS_CHANNEL', 'LOG_LEVEL',
    'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
    'POSTGRES_DATABASE', 'POSTGRES_USERNAME',
    'SESSION_DRIVER', 'SESSION_LIFETIME', 'SESSION_ENCRYPT',
    'SESSION_PATH', 'SESSION_DOMAIN',
    'BROADCAST_CONNECTION', 'FILESYSTEM_DISK',
    'QUEUE_CONNECTION', 'CACHE_STORE',
    'REDIS_CLIENT', 'REDIS_HOST', 'REDIS_PASSWORD', 'REDIS_PORT',
    'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME',
    'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST', 'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME',
    'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_FROM_ADDRESS',
    'MEMCACHED_HOST',
    'AWS_DEFAULT_REGION', 'AWS_USE_PATH_STYLE_ENDPOINT',
    'OLLAMA_URL',
    'CLAUDE_USE_DIRECT_API', 'CLAUDE_USE_OLLAMA_WORKERS',
    'MAYRING_MCP_ENDPOINT', 'MAYRING_OLLAMA_MODEL', 'PI_AGENT_URL',
    'JWT_ISSUER', 'JWT_AUDIENCE', 'JWT_TTL_SECONDS', 'JWT_REFRESH_GRACE_SECONDS',
    'MAYRING_UI_AUTH_PASS', 'MAYRING_UI_AUTH_USER', 'MAYRING_PORT',
    'PAPER_SEARCH_URL',
    'STRIPE_MAYRING_PRICE_ID',
    'MCP_RESTRICT_TO_INTERNAL',
]

env = {k: os.environ[k] for k in KEYS if os.environ.get(k) is not None}

# Multiline-Werte (PEM-Keys) base64-encoden für single-line .env.
for key in ('JWT_PRIVATE_KEY', 'JWT_PUBLIC_KEY'):
    if key in env and '\n' in env[key]:
        env[key] = base64.b64encode(env[key].encode()).decode()

# GitHub blocks GITHUB_ prefix in secret names — remap to correct .env keys
for gh_key, env_key in [('GH_CLIENT_ID', 'GITHUB_CLIENT_ID'), ('GH_CLIENT_SECRET', 'GITHUB_CLIENT_SECRET')]:
    if gh_key in env:
        env[env_key] = env.pop(gh_key)

env_path.write_text('\n'.join(f'{k}={v}' for k, v in env.items()) + '\n')
print(f'[write-env] .env written — {len(env)} keys')

#!/usr/bin/env python3
"""Writes .env from .env.defaults + GitHub Environment variables passed as env vars.
Called by CI/CD deploy workflows. All OVERRIDE_KEYS are injected via workflow env: block.
"""
import os
from pathlib import Path

root = Path(__file__).parent.parent
defaults_path = root / '.env.defaults'
env_path = root / '.env'

OVERRIDE_KEYS = [
    # ── Secrets ─────────────────────────────────────────────────────
    'APP_KEY',
    'DB_PASSWORD', 'POSTGRES_PASSWORD',
    'REVERB_APP_SECRET',
    'CLAUDE_API_KEY',
    'MCP_AUTH_TOKEN', 'MAYRING_MCP_AUTH_TOKEN',
    'PAPER_SEARCH_TOKEN',
    'STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET',
    'GH_CLIENT_ID', 'GH_CLIENT_SECRET',  # GitHub blocks GITHUB_ prefix — mapped below
    'MAIL_PASSWORD',
    'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
    'LANGDOCK_DB_USERNAME', 'LANGDOCK_DB_PASSWORD',
    # ── Variables ────────────────────────────────────────────────────
    'APP_NAME', 'APP_ENV', 'APP_URL', 'APP_PORT', 'APP_DEBUG',
    'DB_HOST', 'DB_DATABASE', 'DB_USERNAME',
    'POSTGRES_DATABASE', 'POSTGRES_USERNAME',
    'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME',
    'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST', 'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME',
    'OLLAMA_URL',
    'MAYRING_MCP_ENDPOINT', 'MAYRING_OLLAMA_MODEL', 'PI_AGENT_URL',
    'MAYRING_UI_AUTH_PASS', 'MAYRING_UI_AUTH_USER', 'MAYRING_PORT',
    'PAPER_SEARCH_URL',
    'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_FROM_ADDRESS',
    'STRIPE_MAYRING_PRICE_ID',
]

overrides = {k: os.environ[k] for k in OVERRIDE_KEYS if os.environ.get(k)}

# GitHub blocks GITHUB_ prefix in secret names — remap to correct .env keys
for gh_key, env_key in [('GH_CLIENT_ID', 'GITHUB_CLIENT_ID'), ('GH_CLIENT_SECRET', 'GITHUB_CLIENT_SECRET')]:
    if gh_key in overrides:
        overrides[env_key] = overrides.pop(gh_key)

defaults_lines = defaults_path.read_text().splitlines() if defaults_path.exists() else []
written: set[str] = set()
out: list[str] = []

for line in defaults_lines:
    stripped = line.strip()
    if stripped and not stripped.startswith('#') and '=' in stripped:
        key = stripped.split('=', 1)[0].strip()
        if key in overrides:
            out.append(f'{key}={overrides[key]}')
            written.add(key)
            continue
    out.append(line)

for key, val in overrides.items():
    if key not in written:
        out.append(f'{key}={val}')

env_path.write_text('\n'.join(out) + '\n')
print(f'[write-env] .env written — {len(overrides)} overrides applied')

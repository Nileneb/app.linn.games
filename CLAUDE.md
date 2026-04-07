# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Initial Setup (lokal ohne Docker)
composer setup               # Install, Key, Migrate, Seed, NPM Build

# Development
composer dev                 # PHP server + Queue + Vite parallel (lokal)
npm run dev                  # Nur Vite dev server
docker compose up -d         # Alle Container (dev, Bind-Mounts via override)
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d  # Port 6480 statt 6481

# Artisan (im Container)
docker compose exec php-cli php artisan migrate
docker compose exec php-cli php artisan tinker

# Testing
docker compose run --rm php-test vendor/bin/pest            # Alle Tests (Docker, empfohlen)
docker compose run --rm php-test vendor/bin/pest --filter="TestName"  # Einzelner Test
docker compose build php-test                               # Nach Änderungen außerhalb tests/
composer test                                               # Lokal ohne Docker

# Build & Deploy
npm run build              # Vite build für Production
./deploy.sh                # Manuelles Deployment (Synology NAS)
./deploy.sh --skip-build   # Ohne Docker-Rebuild
./deploy.sh --skip-migrate # Ohne Migrationen

# Cache & Assets
docker compose exec php-cli php artisan view:clear
docker compose exec php-cli php artisan filament:assets

# MCP Servers
docker compose --profile mcp up -d mcp-paper-search  # Optional: Paper search via SSE
```

## MCP Servers

### Langdock Agent MCP (Master Key Access)

The Langdock MCP server enables direct agent management and API access via MCP interface.

**Setup (local machine):**
```bash
git clone https://github.com/Flissel/langdock-mcp.git
cd langdock-mcp
pip install -r requirements.txt

# Set API key in environment
export LANGDOCK_API_KEY="<your-master-key>"

# Start the MCP server (runs on local machine)
python server.py
```

**Register in Claude Code** (`~/.claude/settings.json`):
```json
{
  "mcpServers": {
    "langdock": {
      "command": "python",
      "args": ["/path/to/langdock-mcp/server.py"],
      "env": {
        "LANGDOCK_API_KEY": "<your-master-key>"
      }
    }
  }
}
```

After restart, Claude Code can:
- Query agent metadata
- Trigger agent completions API
- Manage agent configurations
- Access webhooks and context injection

**Current Agents in Use:**
- `scoping_mapping_agent` — P1 & P2 phases
- `search_agent` — P3 & P4 phases
- `review_agent` — P5, P6, P7 phases

See `LangdockAgentService` and `AgentPayloadService` for implementation details.

### MCP Paper Search (Optional)

Paper search via SSE streaming (Docker profile):
```bash
docker compose --profile mcp up -d mcp-paper-search
```

## Architecture

**app.linn.games** is a Laravel 12 research management platform for AI-assisted systematic literature reviews.

### Stack
- **Backend**: Laravel 12, PHP 8.4, PostgreSQL 16 (pgvector, native Enums), Redis
- **Frontend**: Livewire 3 + Volt (inline components), Tailwind CSS 4, Vite
- **Admin**: Filament 4.9 (Schema-based forms, German labels) — `ContactResource`, `UserResource`, `WorkspaceResource`
- **Auth**: Fortify 1.30 (plain Blade, 2FA support), Spatie Permission (roles)
- **AI**: Langdock Agent (sync + async via Queue), Ollama embeddings (`nomic-embed-text`)
- **WebSockets**: Laravel Reverb (real-time streaming agent responses)

### Docker Compose Setup

- `docker-compose.yml` — Produktions-Images (named volumes, kein Bind-Mount)
- `docker-compose.override.yml` — Wird **automatisch** beim `docker compose up` gemergt; aktiviert Bind-Mounts für Live-Reload
- `docker-compose.dev.yml` — Manuell einbinden (`-f`); ändert Port von 6481 → 6480
- **Production** (kein Override): `docker compose -f docker-compose.yml up -d`

Container für Artisan/CLI-Befehle immer über `php-cli`, nicht `php-fpm`.

### Key Architectural Patterns

**Multi-Tenancy (Workspace)**:
- `Workspace` → `WorkspaceUser` (pivot) → `User`
- `Projekt` belongs to both `User` and `Workspace`
- `WorkspaceResource` in Filament manages workspaces

**Account Approval Flow**:
- New users land on `/pending-approval` until an admin activates their account
- `EnsureAccountIsActive` middleware enforces this on `auth`+`verified` routes

**Domain Models (Recherche)** use custom conventions:
- UUID primary keys via `HasUuids` trait
- German timestamp columns (`erstellt_am`, `letztes_update`) with `$timestamps = false`
- 29 phase-specific models organized in `app/Models/Recherche/` (P1–P8 phases)
- `PhaseAgentResult` stores KI-agent output per phase, keyed by `projekt_id` + `phase`

**Data Flow (KI-Agent)**:

*Synchronous (chat/phase buttons)*:
```
Volt component → LangdockAgentService::call(agentId, messages)
→ POST https://api.langdock.com/agent/v1/chat/completions
→ Agent reads DB via /mcp/sse (VerifyMcpToken Bearer auth)
→ Response stored in PhaseAgentResult, displayed in modal
```

*Asynchronous (queue)*:
```
ProcessPhaseAgentJob → LangdockAgentService::call()
→ Result stored via AgentResultStorageService
→ PhaseChainService triggers next phase job
```

**Credits System**:
- `CreditService` tracks API usage costs per workspace
- `CreditTransaction` model logs debits/credits
- `AgentDailyLimitExceededException` / `InsufficientCreditsException` thrown by `LangdockAgentService`

**Mayring Content Analysis** (P8):
- `ProcessMayringBatchJob` / `ProcessMayringChunkJob` — async qualitative coding of P5 hits
- `ChunkCodierung` model stores coded text chunks
- Route: `GET /recherche/{projekt}/mayring`

**Security Layers**:
- `VerifyMcpToken`: Bearer token for MCP endpoints
- `EnsureAccountIsActive`: blocks pending/inactive users
- `ProjektPolicy`: Owner-only access (never bypass `$this->authorize()`)

**pgvector**: Raw SQL required — Eloquent doesn't support vector types:
```php
DB::statement('INSERT INTO ... (embedding) VALUES (?::vector)', [$array]);
DB::select('SELECT *, 1 - (embedding <=> ?::vector) FROM ...', [$queryVector]);
```

### Known Gaps
- Admin-Panel tests missing

### Conventions
- **Language**: German for comments/commits, English for code
- **No Alpine.js** — only Livewire directives (`wire:model`, `wire:click`)
- **No redirect() helper** — use `$this->redirect(route(...), navigate: true)`
- **Volt routing** — always `Volt::route()`, never `Route::get()`
- **Migrations** in separate commit before code changes
- **Tests**: Pest syntax only, PostgreSQL test database, `User::factory()->withoutTwoFactor()->create()`

### Git Flow
```
feature/* → develop → main (manual deploy)
```
Squash merges, Conventional Commits (`feat:`, `fix:`, `docs:`), no direct-to-main.
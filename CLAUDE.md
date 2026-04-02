# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development
npm run dev              # Start Vite dev server
docker compose up -d     # Start all containers (Dev mit Bind-Mounts)

# Testing
composer test                              # Lokal ohne Docker
docker compose run --rm php-test vendor/bin/pest  # Docker (empfohlen)
docker compose build php-test              # Nach Änderungen außerhalb tests/

# Build & Deploy
npm run build              # Vite build für Production
./deploy.sh                # Manuelles Deployment (Synology NAS)
./deploy.sh --skip-build   # Ohne Docker-Rebuild
./deploy.sh --skip-migrate # Ohne Migrationen

# Cache & Assets
docker compose exec php-fpm php artisan view:clear      # View-Cache leeren
docker compose exec php-fpm php artisan filament:assets # Filament-Assets neu publizieren
```

## Architecture

**app.linn.games** is a Laravel 12 research management platform for AI-assisted systematic literature reviews.

### Stack
- **Backend**: Laravel 12, PHP 8.2+ (CI: 8.4), PostgreSQL 16 (pgvector, native Enums), Redis
- **Frontend**: Livewire 3 + Volt (inline components), Tailwind CSS 4, Vite
- **Admin**: Filament 4.9 (Schema-based forms, German labels)
- **Auth**: Fortify 1.30 (plain Blade, 2FA support)
- **AI**: Langdock Agent (webhook-triggered), Ollama embeddings (`nomic-embed-text`)

### Key Architectural Patterns

**Domain Models (Recherche)** use custom conventions:
- UUID primary keys via `HasUuids` trait
- German timestamp columns (`erstellt_am`, `letztes_update`) with `$timestamps = false`
- 29 phase-specific models organized in `app/Models/Recherche/` (P1–P8 phases)

**Data Flow (KI-Agent)**:
```
User input → Volt component → TriggerLangdockAgent (ShouldBeUnique, 300s) → Redis Queue
→ Langdock Webhook → VerifyLangdockSignature (HMAC + Timestamp ±5min + Nonce-Replay)
→ Agent reads DB via /mcp/sse (VerifyMcpToken Bearer auth)
```

**Security Layers**:
- `VerifyLangdockSignature`: HMAC-SHA256, timestamp validation, Redis nonce cache
- `VerifyMcpToken`: Bearer token for MCP endpoints
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
- **Tests**: Pest syntax only, SQLite in-memory, `User::factory()->withoutTwoFactor()->create()`

### Git Flow
```
feature/* → develop → main (manual deploy)
```
Squash merges, Conventional Commits (`feat:`, `fix:`, `docs:`), no direct-to-main.

# Copilot Instructions — app.linn.games

> Detaillierte Architektur, Datenmodell und Konventionen: siehe `.github/instructions/app_linn_games_custom.instructions.md`
> Docker-Workflow: siehe `.github/instructions/docker-dev-workflow.instructions.md`

## Sprache

Kommuniziere auf Deutsch, Code und Commits auf Englisch.

## Wichtigste Regeln (Kurzform)

- **Stack**: Laravel 12 · Livewire 3 / Volt (inline) · Filament 4.9 · PostgreSQL 16 (pgvector, Enums) · Redis · Pest
- **Kein Alpine.js** — nur Livewire-Direktiven (`wire:model`, `wire:click`)
- **Kein CI/CD-Deploy** — nur manuell via `./deploy.sh`
- **Keine `deploy.yml`** GitHub Actions erstellen
- **Tests**: Pest-Syntax, SQLite in-memory, `User::factory()->withoutTwoFactor()->create()`
- **Docker**: `php-test` ist Baked Image → nach Code-Änderungen `docker compose build php-test`
- **Port**: nginx auf `6481:80` (Dev: `6480:80` via override)
- **Git**: `feature/*` → `develop` → `main`, Squash-Merge, Conventional Commits
- **Migrations** immer in separatem Commit vor Code-Änderungen
- **Sicherheit**: MCP Bearer-Token, pgvector Raw SQL ist Absicht

## Arbeitsweise

- Dokumentiere Architekturentscheidungen in den Instructions
- Pushe regelmäßig nach GitHub (Feature-Branch → develop)
- Neue Features brauchen Feature-Tests, Bugfixes brauchen Regressions-Tests

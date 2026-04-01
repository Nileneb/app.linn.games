# Copilot Instructions — app.linn.games

## Sprache

Kommuniziere auf Deutsch, Code und Commits auf Englisch.

## Stack

- **Backend**: Laravel 12, PHP 8.2+ (8.4 in CI)
- **Frontend**: Livewire 3 / Volt (inline components), Tailwind CSS 4
- **Admin**: Filament 4.9 (Schema-basiert)
- **Datenbank**: PostgreSQL 16 (UUID, pgvector, Custom Enums), Redis (Cache/Session/Queue)
- **AI**: Langdock Webhook → TriggerLangdockAgent Job, Ollama (Embeddings), Paper-Search MCP
- **Auth**: Laravel Fortify + 2FA (plain Blade, kein Livewire)
- **Testing**: Pest (kein PHPUnit-Klassen-Stil), SQLite in-memory
- **Linting**: Laravel Pint (CI-enforced)

## Deployment & CI

- **Kein CI/CD-Deploy** — Deployment erfolgt manuell auf dem Server via `./deploy.sh`
- Tests und Lint laufen über GitHub Actions (`tests.yml`, `lint.yml`)
- Keine `deploy.yml` oder ähnliche automatische Deploy-Workflows erstellen
- Docker auf Synology NAS: `docker compose up -d`

## Architektur-Entscheidungen

### Models
- **UUID** als Primary Key für Domain-Models (Webhook, ChatMessage, Projekt, Phase, …) via `HasUuids` Trait
- **Standard auto-increment id** für User (Fortify-Kompatibilität)
- Custom Timestamps: Recherche-Models nutzen `erstellt_am`, `letztes_update` statt `created_at`/`updated_at`
- Activity Logging: `Spatie\Activitylog\Models\Concerns\LogsActivity` (NICHT `Traits\LogsActivity`)
- Password-Felder: immer `'hashed'` Cast, in `$hidden` und `$fillable`

### Volt / Livewire
- Inline-Komponenten in Blade-Dateien: `new class extends Component { ... }`
- Routing via `Volt::route('path', 'component.name')`
- Kein Alpine.js — nur Livewire-Direktiven (`wire:model`, `wire:click`, `wire:loading`)
- Redirect mit `$this->redirect(route(...), navigate: true)`

### Filament
- Schema-basierte Forms/Tables
- DateTimes formatieren als `d.m.Y H:i`
- NavigationLabel auf Deutsch

### Routing
- Web: Dot-Notation (`recherche.projekt`, `profile.edit`)
- API: Explizite POST/GET, kein `Route::resource()`
- Middleware: `['auth', 'verified']` für geschützte Routen
- Custom Middleware: `VerifyLangdockSignature`, `VerifyMcpToken`

## Datenbank

- **PostgreSQL** ist die Produktions-DB — nutze DB-Features (Enums, pgvector, Extensions)
- Migrations mit `return new class extends Migration`
- pgsql-spezifische Migrations brauchen Guard: `if (DB::getDriverName() !== 'pgsql') return;`
- SQLite-kompatible Test-Tabellen in separater Migration (`2026_03_31_099999`)
- Foreign Keys: `foreignId('user_id')->constrained()->cascadeOnDelete()`
- Neue Enums: `DB::statement("CREATE TYPE ... AS ENUM (...)")` mit `DROP TYPE IF EXISTS ... CASCADE` in down()

## Testing

- **Pest**: `test('beschreibung', function () { ... })` Syntax
- **SQLite in-memory** für Tests — keine PostgreSQL-Features in Tests erwarten
- User Factory: `User::factory()->withoutTwoFactor()->create()`
- Volt Testing: `Volt::test('component.path')->set('prop', 'val')->call('method')`
- Queue Testing: `Queue::fake()` vor Requests die Jobs dispatchen (besonders bei `QUEUE_CONNECTION=sync` in CI)
- Config Override: `Config::set('services.langdock.secret', 'test-secret')`
- Neue Features brauchen Feature-Tests, Bugfixes brauchen Regressions-Tests

## Docker

- 10 Services: web, php-fpm, php-cli, php-test, queue-worker, postgres, redis, postgres-mcp, ollama, mcp-paper-search
- `php-test` ist ein Baked Image — nach Code-Änderungen außerhalb von `tests/`, `phpunit.xml`, `.env` muss `docker compose build php-test` laufen
- Lokale Tests: `docker compose run --rm php-test vendor/bin/pest`
- Port: nginx auf `6481:80`

## Git-Konventionen

- Branches: `feature/`, `fix/`, `docs/`, `refactor/`
- Flow: `feature/*` → `develop` → `main`
- Squash-Merge bevorzugt
- Commit Messages: Conventional Commits (`feat:`, `fix:`, `docs:`, `refactor:`)
- Migrations in separaten Commits vor Code-Änderungen

## Sicherheit

- Webhooks: HMAC-SHA256 Signatur + Timestamp-Validierung (5 Min Toleranz) + Cache-Nonce (Replay-Schutz)
- MCP: Bearer Token Auth via Nginx Proxy
- Ollama: Token Auth via Nginx `/ollama/` Location Block
- Passwörter: `hashed` Cast, niemals im Klartext loggen oder zurückgeben

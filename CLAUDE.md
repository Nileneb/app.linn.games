# CLAUDE.md — app.linn.games

## Quick Ref

```bash
composer dev          # PHP + Queue + Vite parallel
composer test         # Pest lokal
docker compose up -d  # Alle Container
docker compose run --rm php-test vendor/bin/pest  # Tests (Docker)
vendor/bin/pint       # Code-Style
npm run build         # Vite Production
./deploy.sh           # Deploy (Synology NAS)
```

CLI immer via `php-cli`, nie `php-fpm`.

## Stack

Laravel 12 · PHP 8.4 · PostgreSQL 16 (pgvector, native Enums) · Redis · Livewire 3 + Volt · Tailwind 4 · Vite · Filament 4.9 · Fortify 1.30 · Spatie Permission · Langdock Agent API · Ollama (nomic-embed-text) · Laravel Reverb (WebSockets)

## Architektur (Kurzform)

**Multi-Tenancy:** Workspace → WorkspaceUser (pivot) → User. Projekt gehört zu User + Workspace.

**8-Phasen Systematic Review (P1–P8):**
- P1–P4: Fragestellung → Scoping → Datenbankauswahl → Suchstrings (auto-chain)
- P4→P5: KEIN Auto-Chain (manueller Paper-Import nötig)
- P5–P8: Screening → Qualitätsbewertung → Synthese → Abschluss (auto-chain)

**KI-Agent Flow:**
```
UI (Volt/Livewire) → TriggersPhaseAgent trait ODER agent-action-button.blade.php
  → SendAgentMessage → LangdockAgentService::callByConfigKey()
  → LangdockContextInjector::inject() (RLS-Bootstrap: SET LOCAL app.current_projekt_id)
  → POST api.langdock.com/agent/v1/chat/completions
  → Response → AgentPayloadService::persistPayload() (JSON→DB)
  → LangdockArtifactService::persistFromAgentResponse()
  → PhaseAgentResult gespeichert
  → PhaseChainService::maybeDispatchNext() (auto-chain)
```

**Async:** ProcessPhaseAgentJob (Queue) für alle Phasen-Agents.

**RAG Pipeline:**
```
DownloadPaperJob → PdfParserService → IngestPaperJob → EmbeddingService (Ollama)
→ PaperEmbedding (pgvector) → RetrieverService → Agent-Context
```

**Credits:** CreditService trackt Token-Verbrauch pro Workspace. CreditTransaction loggt. Exceptions: InsufficientCreditsException, AgentDailyLimitExceededException.

## Schlüsseldateien

| Bereich | Dateien |
|---------|---------|
| Agent-Aufruf | `app/Services/LangdockAgentService.php`, `app/Actions/SendAgentMessage.php` |
| Context/RLS | `app/Services/LangdockContextInjector.php` |
| Phasen-Job | `app/Jobs/ProcessPhaseAgentJob.php` |
| Phasen-Chain | `app/Services/PhaseChainService.php`, `config/phase_chain.php` |
| Agent-Trigger (UI) | `app/Livewire/Concerns/TriggersPhaseAgent.php`, `resources/views/livewire/recherche/agent-action-button.blade.php` |
| Agent-Config | `config/services.php` (langdock section) |
| Payload→DB | `app/Services/AgentPayloadService.php` |
| Artefakte | `app/Services/LangdockArtifactService.php` |
| Credits | `app/Services/CreditService.php` |
| RAG | `app/Services/RetrieverService.php`, `app/Services/EmbeddingService.php` |
| Synthese | `app/Services/SynthesisMarkdownService.php` |
| Export | `app/Http/Controllers/ProjektExportController.php` |
| Admin | `app/Filament/Resources/` (ContactResource, UserResource, WorkspaceResource) |
| Modelle | `app/Models/Recherche/` (29 Phasen-Modelle P1–P8 + Projekt + Paper*) |
| Auth/Middleware | `EnsureAccountIsActive`, `VerifyMcpToken`, `SecureMcpHeaders`, `ProjektPolicy` |
| Mayring | `app/Jobs/ProcessMayringBatchJob.php`, `app/Jobs/ProcessMayringChunkJob.php` |

## API-Routes (Bearer VerifyMcpToken)

```
POST /api/papers/ingest           → PaperRagController::ingest
GET  /api/papers/rag-search       → PaperRagController::search
POST /api/mcp/agent-call          → McpAgentController (sync)
POST /api/mcp/agent-call/stream   → StreamingMcpController (SSE)
POST /api/webhooks/langdock/agent-result → AgentResultWebhookController (signature-verified)
```

## Langdock Agents

| Config-Key | Phasen |
|------------|--------|
| scoping_mapping_agent | P1, P2 |
| search_agent | P3, P4 |
| review_agent | P5, P6, P7 |

## Model-Konventionen

- UUID Primary Keys (`HasUuids` trait)
- Deutsche Timestamps: `erstellt_am`, `letztes_update` (`$timestamps = false`)
- `Projekt` nutzt `Spatie\Activitylog\LogsActivity`
- pgvector: Raw SQL nötig (kein Eloquent-Support für vector)

## Code-Konventionen

- **Sprache:** Deutsch für Kommentare/Commits, Englisch für Code
- **Kein Alpine.js** — nur Livewire (`wire:model`, `wire:click`)
- **Kein redirect()** — `$this->redirect(route(...), navigate: true)`
- **Volt routing** — `Volt::route()`, nie `Route::get()`
- **Migrations** in separatem Commit vor Code
- **Tests:** Pest, PostgreSQL Test-DB, `User::factory()->withoutTwoFactor()->create()`
- **Git:** `feature/* → develop → main`, Squash Merges, Conventional Commits

## Docker

- `docker-compose.yml` — Produktion (named volumes)
- `docker-compose.override.yml` — Auto-Merge, Bind-Mounts für Dev
- `docker-compose.dev.yml` — Manuell (`-f`), Port 6480 statt 6481
- Production: `docker compose -f docker-compose.yml up -d` (kein Override)

## MCP Server (Langdock)

Lokales Setup: `langdock-mcp` Repo klonen, `LANGDOCK_API_KEY` setzen, `python server.py`. In `~/.claude/settings.json` registrieren. Ermöglicht Agent-Management direkt aus Claude Code.

## Bekannte Lücken

- Admin-Panel Tests fehlen
- TriggersPhaseAgent übergibt keinen $context an SendAgentMessage (→ Issue #154)
- user_id in PhaseChain = Projekt-Ersteller statt aktiver User (→ Issue #154)

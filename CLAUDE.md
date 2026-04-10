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

Laravel 12 · PHP 8.4 · PostgreSQL 16 (pgvector, native Enums) · Redis · Livewire 3 + Volt · Tailwind 4 · Vite · Filament 4.9 · Fortify 1.30 · Spatie Permission · Claude CLI (Anthropic) · Ollama (nomic-embed-text) · Laravel Reverb (WebSockets)

## Architektur (Kurzform)

**Multi-Tenancy:** Workspace → WorkspaceUser (pivot) → User. Projekt gehört zu User + Workspace.

**8-Phasen Systematic Review (P1–P8):**
- P1–P4: Fragestellung → Scoping → Datenbankauswahl → Suchstrings (auto-chain)
- P4→P5: KEIN Auto-Chain (manueller Paper-Import nötig)
- P5–P8: Screening → Qualitätsbewertung → Synthese → Abschluss (auto-chain)

**KI-Agent Flow (4-Agent Architektur):**
```
Main Agent (Chat): StreamingAgentService → ClaudeCliService
  → claude --print --output-format json --append-system-prompt "..."
  → SSE-Chunks an Browser → AgentResultStorageService (Markdown)

Phasen-Agents (P1–P8): ProcessPhaseAgentJob (Queue)
  → ClaudeCliService::callForPhase(agentConfigKey, messages, context)
  → claude --print --output-format json --model {worker-model}
  → AgentResponseParser::parse() (JSON-Envelope → db_payload + md_files)
  → AgentPayloadService::persistPayload() (JSON→DB, RLS-geschützt)
  → LangdockArtifactService::persistFromAgentResponse()
  → PhaseAgentResult gespeichert
  → PhaseChainService::maybeDispatchNext() (auto-chain)
```

**Agents haben KEINEN DB-Zugriff.** Alle DB-Writes gehen durch Laravel-Middleware (AgentPayloadService mit RLS + Whitelist).

**Worker-Agents** (`.claude/agents/worker-{1,2,3}-*.md`): Haiku 4.5, read-only, kein Bash/FS/DB.

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
| Agent-Aufruf | `app/Services/ClaudeCliService.php`, `app/Services/ClaudeService.php`, `app/Actions/SendAgentMessage.php` |
| Agent-Definitionen | `.claude/agents/worker-{1,2,3}-*.md`, `resources/prompts/agents/*.md` |
| Context/RLS | `app/Services/ClaudeContextBuilder.php` |
| Phasen-Job | `app/Jobs/ProcessPhaseAgentJob.php` |
| Phasen-Chain | `app/Services/PhaseChainService.php`, `config/phase_chain.php` |
| Agent-Trigger (UI) | `app/Livewire/Concerns/TriggersPhaseAgent.php`, `resources/views/livewire/recherche/agent-action-button.blade.php` |
| Agent-Config | `config/services.php` (anthropic section) |
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

```

## Agents

| Config-Key | Phasen | Worker |
|------------|--------|--------|
| scoping_mapping_agent | P1, P2 | Worker 1 (Haiku) |
| search_agent | P3, P4 | Worker 2 (Haiku) |
| review_agent | P5, P6, P7 | Worker 3 (Haiku) |
| evaluation_agent | P6 | Worker 3 |
| synthesis_agent | P7 | Worker 3 |
| mayring_agent | P7 (Tool-Use) | Worker 3 |
| chat-agent | Chat/Orchestrator | Main Agent (Sonnet) |

## Model-Konventionen

- UUID Primary Keys (`HasUuids` trait) für alle Domain-Models (Projekt, Phase, P1–P8, Workspace, Contact, ChatMessage, etc.)
- **Ausnahme User:** `unsignedBigInteger` auto-increment (Fortify-Kompatibilität — nicht ändern)
- `user_id` Foreign Keys: immer `unsignedBigInteger`, nie UUID
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

## MCP Memory Server

Persistenter, semantisch durchsuchbarer Memory-Store. **Immer nutzen** für Kontext aus vorherigen Sessions.

```
mcp__memory__search_memory  — Semantische Suche über alle Memories (query, top_k, tags)
mcp__memory__put            — Neue Memory speichern (source, content, tags, scope)
mcp__memory__get            — Chunk by ID abrufen
mcp__memory__list_by_source — Alle Chunks einer Source listen
mcp__memory__invalidate     — Veraltete Memory invalidieren
```

**Wann nutzen:**
- **Session-Start:** `search_memory` mit dem aktuellen Task als Query — holt relevanten Kontext aus vorherigen Sessions
- **Nach Task-Abschluss:** Erkenntnisse, Entscheidungen, Fehler via `put` speichern
- **Source-IDs:** `session-memory:{topic}` für Session-Wissen, `repo:{path}` für Code-Kontext

**Bekannte Source-IDs:**
- `session-memory:agent-architecture` — 4-Agent Design
- `session-memory:docker-setup` — Docker Config & Troubleshooting
- `session-memory:phase-chain-system` — 8 Phasen, Quality Gates
- `session-memory:cli-flags-fix` — Claude CLI Flags
- `session-memory:user-preferences` — User Preferences
- `session-memory:pending-work` — Offene Arbeit & Prioritäten

## Wichtige Skripte — nach Task-Abschluss aktuell halten

- `CLAUDE.md` — Diese Datei. Nach jedem größeren Task aktualisieren.
- `.claude/ARCHITECTURE.md` — Architektur-Übersicht. Bei Strukturänderungen updaten.
- MCP Memory — Erkenntnisse via `mcp__memory__put` persistieren.

## Bekannte Lücken

- Admin-Panel Tests fehlen
- Fake-Streaming in StreamingAgentService (100-Zeichen-Chunks statt echtem stream:true)
- LangdockArtifactService umbenennen zu AgentArtifactService (kosmetisch)

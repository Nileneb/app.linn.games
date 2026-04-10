# Architecture — app.linn.games (IST-Zustand)

> Stand: 10. April 2026. Claude CLI + Claude API (Anthropic) im Einsatz.
> **Nach jedem abgeschlossenen Task diese Datei aktuell halten.**

---

## Überblick

app.linn.games ist eine Research-Management-Plattform für KI-gestützte systematische Literaturrecherche (Systematic Reviews). Nutzer durchlaufen 8 Phasen (P1–P8), wobei Claude-Agents die Arbeit je Phase unterstützen.

### Agent-Hierarchie (aktuell)

```
┌─────────────────────────────────────────────────────────┐
│  MAIN AGENT — Orchestrator + Chat                        │
│  (Sonnet 4.6 | ClaudeCliService → claude --print)       │
│  · User-Chat via SSE-Streaming (StreamingAgentService)   │
│  · Dispatcht W1 / W2 / W3 als Subagents (Queue)         │
│  · Clont Worker wenn stuck (WorkerCloneService)          │
│  · RAG-Zugriff (RetrieverService)                        │
│  · KEIN DB-Zugriff, KEIN Tool-Use                        │
└───────────────┬──────────────┬──────────────────────────┘
                │              │                   │
                ▼              ▼                   ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│  WORKER 1        │ │  WORKER 2        │ │  WORKER 3        │
│  Cluster+Strat.  │ │  Suche+Treffer   │ │  Qual.Vorauswahl │
│  P1 – P2         │ │  P3 – P4         │ │  P5 – P8         │
│                  │ │                  │ │                  │
│ mapping-agent.md │ │ pico-agent.md    │ │ screening-agent  │
│ scoping_mapping_ │ │ search_agent     │ │ quality-agent    │
│   _agent         │ │                  │ │ synthesis-agent  │
│                  │ │                  │ │ mayring-agent    │
│ RAG ✓            │ │ RAG ✓            │ │ RAG ✓            │
│                  │ │                  │ │ MayringMcpClient │
│                  │ │                  │ │ Tool-Use ✓       │
└──────────────────┘ └──────────────────┘ └──────────────────┘
         │                    │                    │
         └────────────────────┴────────────────────┘
                              │
                ┌─────────────▼──────────────┐
                │  RAG-Bus                    │
                │  pgvector (dual-source)     │
                │  paper_embeddings +         │
                │  agent_result_embeddings    │
                │  Redis Cache (TTL 30min)    │
                │  EmbeddingService (Ollama)  │
                └─────────────────────────────┘
```

---

## Agent-System

### Kern: `ClaudeService` (`app/Services/ClaudeService.php`)

**Einziger HTTP-Client für die Anthropic API** (`api.anthropic.com/v1/messages`).

| Methode | Zweck |
|---------|-------|
| `callByConfigKey(configKey, messages, context, maxTokens)` | Synchroner Agent-Call. Löst config-Key auf Prompt-Datei auf, baut System-Prompt inkl. Skills + Kontext. |
| `callWithRetry(...)` | Exponentieller Backoff (3 Versuche, 500ms Basis). |
| `callWithToolUse(...)` | Tool-Use-Loop für `mayring_agent` (max. 10 Iterationen). Führt `search_documents` + `ingest_and_categorize` via `MayringMcpClient` aus. |

**Konfiguration** (`config/services.php → anthropic`):

```php
'anthropic' => [
    'api_key'         => env('CLAUDE_API_KEY'),
    'model'           => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
    'max_tokens'      => (int) env('CLAUDE_MAX_TOKENS', 8192),
    'retry_attempts'  => (int) env('CLAUDE_RETRY_ATTEMPTS', 3),
    'retry_sleep_ms'  => (int) env('CLAUDE_RETRY_SLEEP_MS', 500),
    'agents' => [
        'scoping_mapping_agent' => 'mapping-agent',   // W1: P1–P2
        'search_agent'          => 'pico-agent',       // W2: P3–P4
        'review_agent'          => 'screening-agent',  // W3: P5–P8
        'evaluation_agent'      => 'quality-agent',    // W3
        'synthesis_agent'       => 'synthesis-agent',  // W3
        'mayring_agent'         => 'mayring-agent',    // W3 (Tool-Use)
        'chat-agent'            => 'chat-agent',       // Main Agent Chat
    ],
]
```

---

### Skills-System: `PromptLoaderService` (`app/Services/PromptLoaderService.php`)

Jede Agent-Prompt-Datei unter `resources/prompts/agents/{key}.md` hat **YAML-Frontmatter**:

```yaml
---
skills:
  - rag-retrieval
  - phase-context
  - quality-gate
---

Du bist ein ... (System-Prompt Body)
```

`PromptLoaderService::buildSystemPrompt(key)`:
1. Liest `resources/prompts/agents/{key}.md`
2. Parst YAML-Frontmatter → `skills` Liste
3. Lädt `resources/prompts/skills/{skill}.md` und hängt jeden Skill an
4. Gibt fertigen System-Prompt zurück

**Skills** liegen unter `resources/prompts/skills/`. Der Main Agent hat die meisten Skills, Worker erhalten nur die Skills die sie für ihre Phase brauchen (Context-Minimierung).

---

### Context: `ClaudeContextBuilder` (`app/Services/ClaudeContextBuilder.php`)

Baut einen Markdown-Kontext-Block aus DB-Daten der dem System-Prompt angehängt wird:
- Forschungsfrage, Review-Typ, PICO-Elemente aus `Projekt`
- Phase-spezifische Daten (Einschlusskritierien, Paper-Zähler, vorherige Ergebnisse)
- `structured_output`-Flag für JSON-Envelope-Responses

**Kein DB-Schema-Leakage** — kein SET LOCAL, kein Tabellen-Schema an Agents.

---

## Datenfluss: Chat-Turn (Main Agent)

```
1. POST /chat/stream (User-Nachricht)
   → ChatStreamController

2. StreamingAgentService::stream()
   → ContextProvider::buildMessages()
       - Projekt-Metadaten, Phase, User-Info aus DB
       - RetrieverService::retrieve() → RAG-Chunks (dual-source, Redis-Cache)

3. ClaudeCliService::call(userMessage, context)
   → claude --print --output-format json --append-system-prompt "..."
   → ANTHROPIC_API_KEY aus config('services.anthropic.api_key')
   → Synchroner JSON-Response {result, is_error, total_cost_usd, usage}

4. StreamingAgentService streamt Response als SSE (100-Zeichen-Chunks simuliert)
   [⚠ SCHULD: Fake-Streaming — echtes stream:true fehlt noch]

5. Nach Abschluss:
   → AgentResultStorageService::storeChat() → MD-File
   → IngestAgentResultJob::dispatch() (async, Redis Queue)
```

---

## Datenfluss: Worker-Agent (Queue)

```
1. UI-Button (startPipeline) oder PhaseChain-Auto-Chain
   → ProcessPhaseAgentJob::dispatch(projektId, phaseNr, agentConfigKey, messages, context)

2. Job::handle()
   → prependRetrieverContext()         // RAG-Chunks voranstellen (RetrieverService)
   → ClaudeCliService::callForPhase()  // Claude CLI subprocess
     → PromptLoaderService::buildSystemPrompt(promptFile) + Skills
     → ClaudeContextBuilder::build(context)
     → claude --print --model {worker-model} --output-format json
     → JSON Response {result, cost_usd, usage}

3. Response verarbeiten
   → AgentResponseParser::parse()            // JSON-Envelope → db_payload + md_files
   → AgentPayloadService::persistPayload()   // DB-Writes aus db_payload
   → enrichResponseWithSynthesis()           // Synthesis-MD mit Traceability
   → LangdockArtifactService [⚠ ALT — umbenennen zu AgentArtifactService]
   → AgentResultStorageService (MD-File) → IngestAgentResultJob (Embeddings)
   → PhaseAgentResult::markCompleted()

4. Auto-Chain
   → PhaseChainService::maybeDispatchNext()
     → isValidPhaseResult()            // Quality Gate
     → TransitionValidator::validateTransition()
     → ProcessPhaseAgentJob::dispatch() // nächste Phase
```

---

## Worker-Clone-Mechanismus (implementiert)

`WorkerCloneService` (`app/Services/WorkerCloneService.php`):

```
PhaseChainService::detectStuck(projekt, phaseNr)
  → 3+ failed PhaseAgentResults → stuck

WorkerCloneService::shouldClone(result, projekt) → bool
WorkerCloneService::clone(result, projekt, strategy)
  → CreditService::checkCloneLimit(workspace)
      - Free: max 1 pending Clone
      - Pro: max 3 pending Clones
      - Enterprise: unbegrenzt
  → ProcessPhaseAgentJob::dispatch(..., clone_strategy)
```

**Tier-Spalte:** `workspaces.tier` (enum: free/pro/enterprise, default: free)

---

## Worker 3: MayringCoder-Tool-Use

W3 (`mayring_agent`) ist der einzige Worker mit **Claude Tool-Use**:

```
ClaudeService::callWithToolUse()
  Loop (max 10 Iterationen):
    → Claude sendet tool_use → stop_reason == 'tool_use'
    → MayringMcpClient::searchDocuments(query, categories, top_k)
        → POST http://localhost:8090/search
    → MayringMcpClient::ingestAndCategorize(content, source_id)
        → POST http://localhost:8090/ingest
    → Tool-Result zurück an Claude
  → end_turn → finaler Text-Output
```

**MayringCoder-Server** läuft auf `:8090` (separater Service, Konfiguration via `services.mayring_mcp`).

---

## RAG-Pipeline

### Paper-Ingestion
```
DownloadPaperJob → PdfParserService → IngestPaperJob
  → EmbeddingService::generate() → Ollama (nomic-embed-text, 768 Dims)
  → paper_embeddings (pgvector, HNSW-Index)
```

### Agent-Result-Ingestion
```
AgentResultStorageService::store*()
  → IngestAgentResultJob (async, Redis Queue)
    → Chunking: 500 Wörter, 100 Überlappung
    → EmbeddingService::generate() → Ollama
    → agent_result_embeddings (pgvector)
```

### Retrieval (dual-source, alle Agents)
```
RetrieverService::retrieve(query, projektId, userId, workspaceId)
  → Redis Session-Cache prüfen (TTL 30min)
  → Cache-Miss:
    → EmbeddingService::generate(query) → 768-dim Vector
    → pgvector Search: paper_embeddings + agent_result_embeddings
      (WHERE workspace_id = ? AND projekt_id = ?)
    → Top-K Chunks gerankt → Cache
```

---

## Streaming (aktueller Stand + Schulden)

| Weg | Status |
|-----|--------|
| Chat-Agent (Main) | ⚠ **Fake-Streaming** — 100-Zeichen-Chunks nach vollem Response |
| Worker-Agents | ✅ Queue-basiert, kein Streaming nötig |
| MayringCoder Tool-Use | ✅ Synchron (Tool-Loop) |

**Nächste Priorität:** `ClaudeService::callStreaming()` mit `stream: true` + Generator — `StreamingAgentService` dann auf echtes SSE umstellen.

---

## Markdown-Speicherstruktur

```
storage/app/agent-results/
  └── {workspace_id}/
      └── {user_id}/
          └── {projekt_id}/
              ├── P1__mapping-agent__20260408120000.md
              ├── P2__mapping-agent__20260408130000.md
              ├── P3__pico-agent__20260408140000.md
              ├── P4__pico-agent__20260408150000.md
              ├── P5__screening-agent__20260408150000.md
              └── chat__20260408160000.md
```

---

## Sicherheit

- **Kein direkter DB-Zugriff für Agents**: Nur Projekt-ID, Forschungsfrage, RAG-Chunks
- **Kein DB-Schema-Leakage**: `ClaudeContextBuilder` injiziert keine Tabellenschemata
- **`/mcp/sse` intern only**: Nur für lokale Werkzeuge (Claude Code), nicht für externe Agents
- **User-Isolation**:
  - Dateisystem: `{workspace_id}/{user_id}/` — physische Trennung
  - Embeddings: WHERE-Filter auf `workspace_id + user_id + projekt_id`

---

## Multi-Tenancy & Credits

- **Workspace** → WorkspaceUser (pivot) → User
- **`CreditService`**: Token-Verbrauch pro Workspace, Daily Limits pro Agent-Typ
- **`ClaudeService`**: deductiert Credits nach jedem erfolgreichen API-Call
- **Worker-Clone-Limit**: Per `userTier` — Free 1 / Pro 3 / Enterprise ∞

---

## Schlüssel-Dateien

| Datei | Rolle |
|-------|-------|
| `app/Services/ClaudeCliService.php` | Claude CLI Subprocess (Main Agent Chat) |
| `app/Services/ClaudeService.php` | Anthropic API Client (Phasen-Agents) |
| `app/Services/PromptLoaderService.php` | Skills-System + Prompt-Loader |
| `app/Services/ClaudeContextBuilder.php` | DB-Kontext → System-Prompt |
| `app/Services/StreamingAgentService.php` | Chat-Turn-Orchestrierung + SSE (⚠ Fake-Streaming) |
| `app/Services/WorkerCloneService.php` | Stuck-Detection + Clone-Dispatch mit Tier-Limit |
| `app/Services/ContextProvider.php` | Messages-Builder für Chat-Agent |
| `app/Services/MayringMcpClient.php` | Tool-Use-Client für MayringCoder (:8090) |
| `app/Services/PhaseChainService.php` | Auto-Chain + Quality Gate |
| `app/Services/RetrieverService.php` | pgvector dual-source + Redis Cache |
| `app/Services/EmbeddingService.php` | Ollama nomic-embed-text (768 Dims) |
| `app/Services/AgentPayloadService.php` | JSON-Envelope → DB |
| `app/Services/AgentResultStorageService.php` | MD-File-Speicherung |
| `app/Services/CreditService.php` | Token-Tracking pro Workspace |
| `app/Services/SynthesisMarkdownService.php` | Synthesis-MD mit Traceability |
| `app/Services/TransitionValidator.php` | Phase-Threshold-Checks |
| `app/Services/AgentResponseParser.php` | JSON-Envelope Parser (db_payload + md_files) |
| `app/Actions/SendAgentMessage.php` | Legacy-Wrapper (nicht mehr von ProcessPhaseAgentJob genutzt) |
| `app/Jobs/ProcessPhaseAgentJob.php` | Queue-Job Worker-Agents |
| `app/Jobs/IngestAgentResultJob.php` | MD → Chunks → agent_result_embeddings |
| `resources/prompts/agents/` | Agent-Prompts mit YAML-Skills-Frontmatter |
| `resources/prompts/skills/` | Wiederverwendbare Skill-Blöcke |
| `config/services.php` | Agent config-keys → Prompt-Dateien |
| `config/phase_chain.php` | Phase-Verkettung + Thresholds |

---

## Bekannte Lücken / Schulden

| # | Problem | Priorität |
|---|---------|-----------|
| 1 | ~~AgentResponseParser~~ ✅ Erledigt (19dd5e7) | — |
| 2 | ~~ProcessPhaseAgentJob → ClaudeCliService~~ ✅ Erledigt (e772921) | — |
| 3 | **Fake-Streaming** in `StreamingAgentService` — 100-Zeichen-Chunks statt echtem `stream:true` | Mittel |
| 4 | **`LangdockArtifactService`** umbenennen zu `AgentArtifactService` | Niedrig |
| 5 | **Admin-Panel Tests** fehlen (Filament-Ressourcen) | Niedrig |

---

## Phase-Chain Konfiguration

```php
// config/phase_chain.php
1 => ['next_phase' => 2, 'agent_config_key' => 'scoping_mapping_agent', 'label' => 'P2 Review-Typ'],
2 => ['next_phase' => 3, 'agent_config_key' => 'scoping_mapping_agent', 'label' => 'P3 Quellen'],
3 => ['next_phase' => 4, 'agent_config_key' => 'search_agent',          'label' => 'P4 Suchstrings'],
// P4 → P5: KEIN Auto-Chain (manueller Paper-Import nötig)
5 => ['next_phase' => 6, 'agent_config_key' => 'review_agent',          'label' => 'P6 Qualität'],
6 => ['next_phase' => 7, 'agent_config_key' => 'evaluation_agent',      'label' => 'P7 Synthese'],
7 => ['next_phase' => 8, 'agent_config_key' => 'synthesis_agent',       'label' => 'P8 Abschluss'],
```

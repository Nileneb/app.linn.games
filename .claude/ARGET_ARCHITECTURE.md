# Architecture — app.linn.games (IST-Zustand)

> Stand: April 2026. Diese Datei beschreibt den **tatsächlich implementierten** Zustand.
> Für den geplanten Zielzustand siehe `.claude/ROADMAP.md`.

---

## Überblick

app.linn.games ist eine Research-Management-Plattform für KI-gestützte systematische Literaturrecherche (Systematic Reviews). Nutzer durchlaufen 8 Phasen (P1–P8), wobei Langdock-KI-Agents die Arbeit je Phase unterstützen.

```
┌─────────────────────────────────────────────────────────────┐
│                    BENUTZER (Browser)                        │
│  Livewire/Volt UI → Blade-Komponenten pro Phase (P1–P8)    │
└──────────────┬──────────────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────────────┐
│              AGENT-DISPATCH (2 Wege)                         │
│                                                              │
│  Weg A: TriggersPhaseAgent Trait (Livewire-Komponente)      │
│         → AgentPromptBuilder → SendAgentMessage             │
│         → LangdockAgentService::callByConfigKey()           │
│         → Ergebnis direkt in PhaseAgentResult gespeichert   │
│                                                              │
│  Weg B: agent-action-button.blade.php (Blade-Button)        │
│         → ProcessPhaseAgentJob::dispatch() (Queue)          │
│         → SendAgentMessage → LangdockAgentService           │
│         → AgentPayloadService::persistPayload()             │
│         → LangdockArtifactService::persistFromAgentResponse │
│         → PhaseChainService::maybeDispatchNext()            │
└──────────────┬──────────────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────────────┐
│              LANGDOCK API (extern, DSGVO-konform)            │
│  POST https://api.langdock.com/agent/v1/chat/completions   │
│                                                              │
│  3 Agent-Rollen (jeweils eigene Langdock-Agent-ID):         │
│    • scoping_mapping_agent  → P1–P3                         │
│    • search_agent           → P4                            │
│    • review_agent           → P5–P8                         │
│  + Spezial-Agents: retrieval, evaluation, pico, synthesis,  │
│    mayring, master_research_agent                           │
│                                                              │
│  Agents greifen auf die DB zu via:                          │
│    /mcp/sse (PostgreSQL MCP, langdock_agent DB-User, RLS)   │
└─────────────────────────────────────────────────────────────┘
```

---

## Kern-Services und ihre Aufgaben

### 1. LangdockAgentService (`app/Services/`)
- **Einziger HTTP-Client** für Langdock API
- `call(agentId, messages, timeout, context)` — synchroner Call
- `callByConfigKey(configKey, messages)` — löst Agent-ID aus `config/services.php` auf
- Exponentieller Backoff-Retry bei 5xx/Connection-Errors
- Token-Schätzung und Credit-Deduction via `CreditService`

### 2. LangdockContextInjector (`app/Services/`)
- Injiziert RLS-Bootstrap (`SET LOCAL app.current_projekt_id`) in die Messages
- Fügt Phasen-Schema-Snippets hinzu (tabellenorientiert pro Phase)
- Hängt Kontext-Metadaten an (projekt_id, workspace_id, user_id, triggerword)
- Wird automatisch von `LangdockAgentService::call()` aufgerufen

### 3. AgentPromptBuilder (`app/Services/`)
- Baut System- und User-Prompts für den `TriggersPhaseAgent`-Weg
- Nutzt `config/phase_chain.php` für Phase-Metadaten

### 4. ProcessPhaseAgentJob (`app/Jobs/`)
- **Queue-basierter** Agent-Aufruf (Weg B)
- Prepends Retriever-Kontext (RAG-Chunks) vor Agent-Call
- Parst Agent-Response: `parseStructuredResponse()` erkennt JSON-Envelope (`meta/result/db`) oder Freitext
- Persistiert via `AgentPayloadService` und `LangdockArtifactService`
- Generiert Synthesis-Markdown mit Quellentraceability (`SynthesisMarkdownService`)
- Ruft am Ende `PhaseChainService::maybeDispatchNext()` auf

### 5. PhaseChainService (`app/Services/`)
- **Auto-Chain-Logik**: Dispatcht die nächste Phase nach erfolgreichem Abschluss
- Config-gesteuert via `config/phase_chain.php` (next_phase, agent_config_key, label)
- **Quality Gate** (`isValidPhaseResult`): Blockt Chain bei < 100 Zeichen oder Confirmation-Only-Responses
- **Transition Validation**: Nutzt `TransitionValidator` + `PhaseCountService` für Threshold-Checks
- `buildMessages()`: Baut Kontext für die nächste Phase (Projektdaten + vorherige Ergebnisse + Dokumentzähler)

### 6. AgentPayloadService (`app/Services/`)
- Persistiert `db_payload` aus Agent-Responses in die Phasen-Tabellen
- Unterstützt das JSON-Envelope-Format (`meta/result/db`)

### 7. CreditService (`app/Services/`)
- Trackt Token-Verbrauch pro Workspace
- `assertHasBalance()` — wirft `InsufficientCreditsException`
- `deduct()` — zieht Credits ab und loggt in `credit_transactions`
- Daily Limits pro Agent-Typ (konfigurierbar via ENV)

### 8. SynthesisMarkdownService (`app/Services/`)
- Generiert Markdown mit HTML-Comments für Quellentraceability
- Format: `<!-- paper_id: UUID; chunk_index: N; similarity: 0.XX -->`
- Wird in `ProcessPhaseAgentJob::enrichResponseWithSynthesis()` aufgerufen

### 9. RetrieverService (`app/Services/`)
- Vektor-Suche in `paper_embeddings` via pgvector
- `retrieve(query, projektId)` → relevante Chunks
- `formatAsContext(chunks)` → Text-Kontext für Agent-Messages

---

## Datenfluss: Agent-Aufruf (Weg B — Queue, Standard)

```
1. UI-Button-Klick
   → ProcessPhaseAgentJob::dispatch(projektId, phaseNr, agentConfigKey, messages, context)

2. Job::handle()
   → prependRetrieverContext()         // RAG-Chunks voranstellen
   → SendAgentMessage::execute()       // delegiert an LangdockAgentService
     → LangdockContextInjector::inject()  // RLS-Bootstrap + Schema
     → HTTP POST → Langdock API
     → Response

3. Response verarbeiten
   → parseStructuredResponse()         // JSON-Envelope oder null
   → AgentPayloadService::persistPayload()  // DB-Writes aus db_payload
   → enrichResponseWithSynthesis()     // Synthesis-MD mit Traceability
   → LangdockArtifactService::persistFromAgentResponse()  // MD-Dateien speichern
   → PhaseAgentResult::markCompleted()

4. Auto-Chain
   → PhaseChainService::maybeDispatchNext()
     → isValidPhaseResult()            // Quality Gate
     → TransitionValidator::validateTransition()  // Thresholds
     → ProcessPhaseAgentJob::dispatch()  // nächste Phase
```

---

## Phase-Chain Konfiguration

Die Phase-Verkettung ist in `config/phase_chain.php` definiert:

```php
// Beispiel:
1 => ['next_phase' => 2, 'agent_config_key' => 'scoping_mapping_agent', 'label' => 'P2 Review-Typ'],
2 => ['next_phase' => 3, 'agent_config_key' => 'scoping_mapping_agent', 'label' => 'P3 Quellen'],
3 => ['next_phase' => 4, 'agent_config_key' => 'search_agent',          'label' => 'P4 Suchstrings'],
// P4 → P5: KEIN Auto-Chain (manueller Paper-Import nötig)
5 => ['next_phase' => 6, 'agent_config_key' => 'review_agent',          'label' => 'P6 Qualität'],
// ...
```

**Besonderheit P4→P5**: Kein Eintrag für Phase 4 → kein Auto-Chain. Der Nutzer muss erst Papers importieren, bevor P5 starten kann. Die `TransitionValidator`-Thresholds prüfen zusätzlich Mindestanzahlen.

---

## RAG-Pipeline (Paper-Ingestion)

```
DownloadPaperJob
  → PdfParserService (smalot/pdfparser)
  → IngestPaperJob
    → Text in Chunks aufteilen (500 Wörter, 100 Überlappung)
    → EmbeddingService → Ollama (nomic-embed-text)
    → PaperEmbedding (pgvector, IVFFlat-Index)
  → RetrieverService (Vektor-Suche) → Agent-Context
```

---

## Multi-Tenancy & Credits

- **Workspace** → WorkspaceUser (pivot) → User
- **Projekt** belongs to User + Workspace
- **RLS**: `SET LOCAL app.current_projekt_id` / `app.current_workspace_id` auf DB-Ebene
- **Credits**: Pro Workspace, Token-basiert, mit Daily Limits pro Agent-Typ
- **DB-User**: `langdock_agent` — eingeschränkter Zugriff, kein BYPASSRLS

---

## Agent-Response-Formate

Agents können in zwei Formaten antworten:

### A) Freitext (Standard)
Agent antwortet mit normalem Text/Markdown. Wird direkt als `PhaseAgentResult.content` gespeichert.

### B) JSON-Envelope (bei structured_output=true)
```json
{
  "meta": {"projekt_id": "...", "workspace_id": "...", "triggerword": "...", "version": 1},
  "result": {"type": "phase_result", "summary": "...", "data": {"md_files": [...]}},
  "db": {"bootstrapped": true, "loaded": ["p1_komponenten"]},
  "warnings": []
}
```

Parsing in `ProcessPhaseAgentJob::parseStructuredResponse()`:
- Erkennt `meta/result`-Struktur ODER flaches `data`-Objekt
- Gibt `null` zurück bei Freitext → kein db_payload-Processing

---

## Schlüssel-Dateien

| Datei | Rolle |
|-------|-------|
| `app/Services/LangdockAgentService.php` | HTTP-Client für Langdock API |
| `app/Services/LangdockContextInjector.php` | RLS-Bootstrap + Schema-Injection |
| `app/Services/AgentPromptBuilder.php` | System/User-Prompts (Weg A) |
| `app/Services/PhaseChainService.php` | Auto-Chain + Quality Gate |
| `app/Services/AgentPayloadService.php` | JSON→DB Persistenz |
| `app/Services/CreditService.php` | Token-Tracking pro Workspace |
| `app/Services/SynthesisMarkdownService.php` | Synthesis-MD mit Traceability |
| `app/Services/RetrieverService.php` | pgvector-Suche |
| `app/Services/TransitionValidator.php` | Phase-Threshold-Checks |
| `app/Services/PhaseCountService.php` | Zählt Phase-Daten für Thresholds |
| `app/Jobs/ProcessPhaseAgentJob.php` | Queue-Job für Agent-Calls |
| `app/Actions/SendAgentMessage.php` | Wrapper für Agent-Aufruf |
| `app/Livewire/Concerns/TriggersPhaseAgent.php` | Trait für Livewire-Dispatch (Weg A) |
| `config/phase_chain.php` | Phase-Verkettung + Thresholds |
| `config/services.php` | Agent-IDs + API-Keys |

---

## Bekannte Duplikate / Architektur-Schulden

1. **Kontext-Aufbau existiert 3x**: `AgentPromptBuilder`, `PhaseChainService::buildMessages()`, `agent-action-button.blade.php`. Sollte in einen zentralen Builder konsolidiert werden.
2. **Dispatch-Wege A + B**: `TriggersPhaseAgent` (synchron) und `ProcessPhaseAgentJob` (async) haben unterschiedliche Post-Processing-Logik. Weg A fehlt: Retriever-Context, Synthesis-Enrichment, AgentPayloadService.
3. **Quality Gate nur in Auto-Chain**: `isValidPhaseResult()` wird nur bei Chain-Dispatch geprüft, nicht bei manuellem Trigger (Weg A).

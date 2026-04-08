# Architecture — app.linn.games (IST-Zustand)

> Stand: April 2026. Diese Datei beschreibt den **tatsächlich implementierten** Zustand nach der MCP-Architektur-Migration.
> Historischer Entwurf der Zielarchitektur: `.claude/TARGET_ARCHITECTURE.md`

---

## Überblick

app.linn.games ist eine Research-Management-Plattform für KI-gestützte systematische Literaturrecherche (Systematic Reviews). Nutzer durchlaufen 8 Phasen (P1–P8), wobei Langdock-KI-Agents die Arbeit je Phase unterstützen.

Die Plattform unterscheidet zwischen zwei Agent-Typen:
- **Chat-Agent ("Frontdesk")**: Beantwortet User-Fragen, erklärt Phasen-Ergebnisse — kommuniziert via echtem MCP-Streaming
- **Worker-Agents**: Führen Phasen-Arbeit (P1–P8) automatisiert aus — Queue-basiert, kein direkter DB-Zugriff für Langdock

---

## Architektur-Übersicht (Kommunikationsflow)

```
┌─────────────────────────────────────────────────────────────┐
│                    BENUTZER (Browser)                        │
│  Livewire Chat-Komponente (SSE-Streaming)                   │
└──────────────┬──────────────────────────────────────────────┘
               │ POST /chat/stream
               ▼
┌──────────────────────────────────────────────────────────────┐
│  ChatStreamController                                         │
│    → StreamingAgentService::stream()                         │
│      → ContextProvider::buildMessages()                      │
│          Projekt-Metadaten + aktuelle Phase + User-Info      │
│          + RAG-Chunks (RetrieverService: dual-source)        │
│      → LangdockMcpClient::streamChatCompletion()            │
│          → echtes Streaming (MCP-Protokoll)                  │
│      → SSE-Response an Browser                               │
│      → AgentResultStorageService::storeChat()               │
│          → MD-File speichern                                 │
│          → IngestAgentResultJob::dispatch() (async)          │
└──────────────┬───────────────────────────────────────────────┘
               │ (Worker-Agent-Flow: Queue)
               ▼
┌─────────────────────────────────────────────────────────────┐
│  WORKER-AGENT-DISPATCH (Queue-basiert)                       │
│                                                              │
│  PhaseChainService / User-Trigger                           │
│    → ProcessPhaseAgentJob (Redis Queue)                      │
│      → LangdockAgentService::call() (API, kein MCP)         │
│        → Langdock API (kein direkter DB-Zugriff)            │
│      → AgentResultStorageService speichert MD-File          │
│      → IngestAgentResultJob (Embeddings async)              │
│      → PhaseChainService::maybeDispatchNext()               │
└─────────────────────────────────────────────────────────────┘

SICHERHEIT:
  - /mcp/sse nur intern (kein Cloud-Agent-Zugriff)
  - langdock_agent DB-User deaktiviert
  - Kein DB-Schema-Leakage an Langdock
```

---

## Kern-Services (aktueller Stand)

### 1. LangdockMcpClient (`app/Services/LangdockMcpClient.php`) — NEU
- **MCP-Protokoll-Client** für den offiziellen Langdock MCP-Server
- `streamChatCompletion(messages)` — echtes Streaming (Server-Sent Events)
- Ersetzt den alten Fake-Streaming-Ansatz (char-by-char nach vollem Response)
- Zuständig ausschließlich für den Chat-Agent ("Frontdesk")

### 2. ContextProvider (`app/Services/ContextProvider.php`) — NEU (ersetzt LangdockContextInjector)
- Baut System-Messages für Chat-Agent-Requests
- Lädt aus DB: Projekt-Metadaten, aktuelle Phase, User-Info
- Integriert RAG-Chunks via `RetrieverService` (dual-source)
- Kein DB-Schema-Leakage — Agents erhalten nur den nötigsten Kontext

### 3. StreamingAgentService (`app/Services/StreamingAgentService.php`) — AKTUALISIERT
- Orchestriert den gesamten Chat-Turn
- Ruft `ContextProvider::buildMessages()` → `LangdockMcpClient::streamChatCompletion()` auf
- Streamt SSE-Response an Browser
- Übergibt Ergebnis nach Abschluss an `AgentResultStorageService`

### 4. IngestAgentResultJob (`app/Jobs/IngestAgentResultJob.php`) — NEU
- Verarbeitet MD-Files (Phasen-Outputs + Chat-Protokolle) nach dem Speichern
- Chunking: 500 Wörter, 100 Wörter Überlappung
- `EmbeddingService::generate()` → Ollama (nomic-embed-text)
- Schreibt Chunks in `agent_result_embeddings` (pgvector)

### 5. AgentResultStorageService (`app/Services/AgentResultStorageService.php`) — ERWEITERT
- Speichert MD-Files für Phasen-Outputs und Chat-Protokolle
- `storeChat()` — persistiert Chat-Konversation als MD-File
- Dispatcht nach jedem Save automatisch `IngestAgentResultJob`
- Dateiname-Konvention: `P{n}__{agent}__{timestamp}.md` bzw. `chat__{timestamp}.md`

### 6. RetrieverService (`app/Services/RetrieverService.php`) — ERWEITERT (dual-source)
- Vektor-Suche auf **zwei Quellen** gleichzeitig:
  - `paper_embeddings` (Paper-Inhalte, via pgvector)
  - `agent_result_embeddings` (Phasen-Outputs + Chat-Protokolle, neu)
- Session-Cache via Redis (TTL 30 Minuten) — vermeidet redundante Embedding-Berechnung
- `retrieve(query, projektId)` → Top-K Chunks aus beiden Quellen
- `formatAsContext(chunks)` → Text-Kontext für Agent-Messages
- User-Isolation: WHERE-Filter auf `workspace_id` + `user_id` + `projekt_id`

### 7. LangdockAgentService (`app/Services/LangdockAgentService.php`) — UNVERÄNDERT (Worker-Agents)
- **Einziger HTTP-Client** für Langdock API (Worker-Agents)
- `call(agentId, messages, timeout, context)` — synchroner Call
- `callByConfigKey(configKey, messages)` — löst Agent-ID aus `config/services.php` auf
- Exponentieller Backoff-Retry bei 5xx/Connection-Errors
- Token-Schätzung und Credit-Deduction via `CreditService`
- **Wird nicht** für Chat verwendet — nur für Worker-Agents (ProcessPhaseAgentJob)

### 8. AgentPromptBuilder (`app/Services/AgentPromptBuilder.php`)
- Baut System- und User-Prompts für Worker-Agent-Calls
- Nutzt `config/phase_chain.php` für Phase-Metadaten

### 9. ProcessPhaseAgentJob (`app/Jobs/ProcessPhaseAgentJob.php`)
- **Queue-basierter** Worker-Agent-Aufruf
- Prepends Retriever-Kontext (RAG-Chunks) vor Agent-Call
- Parst Agent-Response: `parseStructuredResponse()` erkennt JSON-Envelope (`meta/result/db`) oder Freitext
- Persistiert via `AgentPayloadService` und `LangdockArtifactService`
- Generiert Synthesis-Markdown via `SynthesisMarkdownService`
- Ruft `AgentResultStorageService` → `IngestAgentResultJob` auf
- Ruft `PhaseChainService::maybeDispatchNext()` auf

### 10. PhaseChainService (`app/Services/PhaseChainService.php`)
- **Auto-Chain-Logik**: Dispatcht die nächste Phase nach erfolgreichem Abschluss
- Config-gesteuert via `config/phase_chain.php` (next_phase, agent_config_key, label)
- **Quality Gate** (`isValidPhaseResult`): Blockt Chain bei < 100 Zeichen oder Confirmation-Only-Responses
- **Transition Validation**: Nutzt `TransitionValidator` + `PhaseCountService` für Threshold-Checks
- `buildMessages()`: Baut Kontext für die nächste Phase (Projektdaten + vorherige Ergebnisse + Dokumentzähler)

### 11. AgentPayloadService (`app/Services/AgentPayloadService.php`)
- Persistiert `db_payload` aus Worker-Agent-Responses in die Phasen-Tabellen
- Unterstützt das JSON-Envelope-Format (`meta/result/db`)

### 12. CreditService (`app/Services/CreditService.php`)
- Trackt Token-Verbrauch pro Workspace
- `assertHasBalance()` — wirft `InsufficientCreditsException`
- `deduct()` — zieht Credits ab und loggt in `credit_transactions`
- Daily Limits pro Agent-Typ (konfigurierbar via ENV)

### 13. SynthesisMarkdownService (`app/Services/SynthesisMarkdownService.php`)
- Generiert Markdown mit HTML-Comments für Quellentraceability
- Format: `<!-- paper_id: UUID; chunk_index: N; similarity: 0.XX -->`
- Wird in `ProcessPhaseAgentJob::enrichResponseWithSynthesis()` aufgerufen

---

## Datenfluss: Chat-Turn (aktuell)

```
1. POST /chat/stream (User-Nachricht)
   → ChatStreamController

2. StreamingAgentService::stream()
   → ContextProvider::buildMessages()
       - Projekt-Metadaten aus DB laden
       - Aktuelle Phase + User-Info laden
       - RetrieverService::retrieve() aufrufen:
           - Session-Cache prüfen (Redis, TTL 30min)
           - Cache-Miss: EmbeddingService::generate(user_nachricht)
             → pgvector Search auf agent_result_embeddings + paper_embeddings
             → Top-K Chunks in Cache schreiben
       - System-Message bauen (Kontext + Chunks)

3. LangdockMcpClient::streamChatCompletion()
   → Echtes Streaming via MCP-Protokoll
   → SSE-Response zurück an Browser (ReadableStream in Livewire-Komponente)

4. Nach Abschluss des Streams:
   → AgentResultStorageService::storeChat()
       - MD-File: storage/app/agent-results/{workspace_id}/{user_id}/{projekt_id}/chat__{timestamp}.md
       - IngestAgentResultJob::dispatch() (async, Redis Queue)
   → chat_messages in DB aktualisieren
```

---

## Datenfluss: Worker-Agent (Queue, unverändert)

```
1. UI-Button-Klick oder Auto-Chain
   → ProcessPhaseAgentJob::dispatch(projektId, phaseNr, agentConfigKey, messages, context)

2. Job::handle()
   → prependRetrieverContext()         // RAG-Chunks voranstellen
   → SendAgentMessage::execute()       // delegiert an LangdockAgentService
     → HTTP POST → Langdock API
     → Response

3. Response verarbeiten
   → parseStructuredResponse()         // JSON-Envelope oder null
   → AgentPayloadService::persistPayload()  // DB-Writes aus db_payload
   → enrichResponseWithSynthesis()     // Synthesis-MD mit Traceability
   → LangdockArtifactService::persistFromAgentResponse()  // MD-Dateien speichern
   → AgentResultStorageService (MD-File) → IngestAgentResultJob (Embeddings)
   → PhaseAgentResult::markCompleted()

4. Auto-Chain
   → PhaseChainService::maybeDispatchNext()
     → isValidPhaseResult()            // Quality Gate
     → TransitionValidator::validateTransition()  // Thresholds
     → ProcessPhaseAgentJob::dispatch()  // nächste Phase
```

---

## RAG-Pipeline (erweitert — dual-source)

### Paper-Ingestion (unverändert)

```
DownloadPaperJob
  → PdfParserService (smalot/pdfparser)
  → IngestPaperJob
    → Text in Chunks aufteilen (500 Wörter, 100 Überlappung)
    → EmbeddingService → Ollama (nomic-embed-text)
    → PaperEmbedding (pgvector, IVFFlat-Index)
```

### Agent-Result-Ingestion (neu)

```
AgentResultStorageService::store*()
  → IngestAgentResultJob (async, Redis Queue)
    → Text in Chunks aufteilen (500 Wörter, 100 Überlappung)
    → EmbeddingService::generate() → Ollama (nomic-embed-text)
    → agent_result_embeddings (pgvector)
      Spalten: id, workspace_id, user_id, projekt_id, chunk_text, embedding, source_file, created_at
```

### Retrieval (dual-source)

```
RetrieverService::retrieve(query, projektId, userId, workspaceId)
  → Session-Cache prüfen (Redis, TTL 30min)
  → Cache-Miss:
    → Parallel: pgvector Search auf paper_embeddings + agent_result_embeddings
      (WHERE workspace_id = ? AND user_id = ? AND projekt_id = ?)
    → Top-K Chunks gerankt und zusammengeführt
    → In Session-Cache schreiben
```

---

## Markdown-Speicherstruktur

```
storage/app/agent-results/
  └── {workspace_id}/
      └── {user_id}/
          └── {projekt_id}/
              ├── P1__scoping_agent__20260408120000.md    ← Phasen-Output
              ├── P2__search_agent__20260408130000.md
              ├── chat__20260408140000.md                 ← Chat-Protokoll (pro Session)
              └── chat__20260408150000.md
```

| Typ | Dateiname-Muster | Inhalt |
|-----|------------------|--------|
| **Phasen-Output** | `P{n}__{agent}__{timestamp}.md` | Strukturiertes Ergebnis einer Phase |
| **Chat-Protokoll** | `chat__{timestamp}.md` | Konversation User+Assistant, chronologisch, pro Session (neue Datei bei >30min Inaktivität) |

---

## Sicherheit

- **Kein direkter DB-Zugriff für Langdock-Agents**: Agents erhalten nur den nötigsten Kontext pro Request (Projekt-ID, Forschungsfrage, RAG-Chunks)
- **`langdock_agent` DB-User deaktiviert**: War nur für den alten MCP-Endpoint nötig
- **`/mcp/sse` intern only**: Nur noch für lokale Werkzeuge (z.B. Claude Code) erreichbar, nicht für Langdock-Cloud-Agents
- **Kein DB-Schema-Leakage**: `ContextProvider` injiziert keine Tabellenschemata in Agent-Messages (löst Issues #84, #124)
- **User-Isolation**:
  - Dateisystem: Ordner `{workspace_id}/{user_id}/` — physische Trennung
  - Embeddings (DB): WHERE-Filter auf `workspace_id` + `user_id` bei jedem Retrieval
  - RLS optional für defense-in-depth ergänzbar

---

## Multi-Tenancy & Credits

- **Workspace** → WorkspaceUser (pivot) → User
- **Projekt** belongs to User + Workspace
- **RLS**: `SET LOCAL app.current_projekt_id` / `app.current_workspace_id` auf DB-Ebene (intern, nicht für Langdock-Agents)
- **Credits**: Pro Workspace, Token-basiert, mit Daily Limits pro Agent-Typ
- UUID Primary Keys (`HasUuids` Trait), Deutsche Timestamps (`erstellt_am`, `letztes_update`)

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

## Schlüssel-Dateien (aktueller Stand)

| Datei | Rolle |
|-------|-------|
| `app/Services/LangdockMcpClient.php` | MCP-Protokoll-Client, echtes Chat-Streaming |
| `app/Services/ContextProvider.php` | System-Kontext-Builder für Chat-Agent (ersetzt LangdockContextInjector) |
| `app/Services/StreamingAgentService.php` | Chat-Turn-Orchestrierung + SSE |
| `app/Services/LangdockAgentService.php` | HTTP-Client für Langdock API (Worker-Agents) |
| `app/Services/AgentPromptBuilder.php` | System/User-Prompts (Worker-Agents) |
| `app/Services/PhaseChainService.php` | Auto-Chain + Quality Gate |
| `app/Services/AgentPayloadService.php` | JSON→DB Persistenz (Worker-Agent-Responses) |
| `app/Services/AgentResultStorageService.php` | MD-File-Speicherung (Phasen-Outputs + Chat-Protokolle) |
| `app/Services/CreditService.php` | Token-Tracking pro Workspace |
| `app/Services/SynthesisMarkdownService.php` | Synthesis-MD mit Traceability |
| `app/Services/RetrieverService.php` | pgvector-Suche (dual-source + Redis Cache) |
| `app/Services/EmbeddingService.php` | Ollama-Embedding-Generierung |
| `app/Services/TransitionValidator.php` | Phase-Threshold-Checks |
| `app/Services/PhaseCountService.php` | Zählt Phase-Daten für Thresholds |
| `app/Jobs/ProcessPhaseAgentJob.php` | Queue-Job für Worker-Agent-Calls |
| `app/Jobs/IngestAgentResultJob.php` | MD-File → Chunks → agent_result_embeddings |
| `app/Jobs/IngestPaperJob.php` | PDF-Text → Chunks → paper_embeddings |
| `app/Actions/SendAgentMessage.php` | Wrapper für Worker-Agent-Aufruf |
| `app/Http/Controllers/ChatStreamController.php` | SSE-Endpoint für Chat-Streaming |
| `app/Livewire/Concerns/TriggersPhaseAgent.php` | Trait für Livewire-Dispatch (Worker-Agents) |
| `config/phase_chain.php` | Phase-Verkettung + Thresholds |
| `config/services.php` | Agent-IDs + API-Keys (Langdock) |

---

## Agent-Response-Formate (Worker-Agents)

Worker-Agents können in zwei Formaten antworten:

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

## Bekannte Lücken / Architektur-Schulden

**Gelöst durch die Migration:**
- ~~Kontext-Aufbau 3x~~ (`AgentPromptBuilder`, `PhaseChainService::buildMessages()`, `agent-action-button.blade.php`) → konsolidiert im `ContextProvider`
- ~~Kein echtes Streaming~~ → `LangdockMcpClient` + `StreamingAgentService` (Issue #58)
- ~~DB-Schema-Leakage an Agents~~ → `ContextProvider` ohne Schema-Injection (Issues #84, #124)
- ~~Direkter DB-Zugriff für Langdock-Cloud~~ → `/mcp/sse` intern only, `langdock_agent` deaktiviert (Issue #137)
- ~~Chat-History nur in DB, nicht RAG-nutzbar~~ → MD-Files + `agent_result_embeddings`

**Aktuell bekannte Lücken:**
1. **Admin-Panel Tests fehlen** — keine Pest-Tests für Filament-Ressourcen
2. **TriggersPhaseAgent übergibt keinen `$context` an SendAgentMessage** (Issue #154) — Worker-Agent-Weg A hat weniger Post-Processing als Weg B (kein Retriever-Kontext, kein Synthesis-Enrichment, kein AgentPayloadService)
3. **`user_id` in PhaseChain = Projekt-Ersteller** statt aktiver User (Issue #154)
4. **Quality Gate nur in Auto-Chain**: `isValidPhaseResult()` wird nur bei Chain-Dispatch geprüft, nicht bei manuellem Trigger (TriggersPhaseAgent)

# Target Architecture: MCP-basierte Agent-Kommunikation + Markdown-RAG

> **Status:** Entwurf — ersetzt die bisherige API-basierte Architektur  
> **Datum:** 2026-04-08  
> **Bezug:** Issues #58, #84, #91, #121, #124, #136, #137

---

## 1. Ausgangslage (IST)

### Kommunikation Laravel ↔ Langdock

```
User → Livewire → LangdockAgentService::call()
                    → HTTP POST api.langdock.com/agent/v1/chat/completions
                    → synchrone Antwort (bis 120s Timeout)
                    → StreamingAgentService zerlegt Antwort char-by-char (Fake-Streaming)
```

**Probleme:**
- Kein echtes Streaming — User sieht 30-120s lang nichts
- `LangdockContextInjector` injiziert DB-Schemata + RLS-Bootstrap als User-Message
- Langdock-Agents haben direkten Zugriff auf PostgreSQL via MCP-Endpoint (`/mcp/sse`)
- Keine saubere Trennung zwischen Chat-Agent und Worker-Agents
- Chat-History nur in `chat_messages`-Tabelle, nicht für RAG nutzbar

### Betroffene Dateien (aktuell)

| Datei | Verantwortung |
|---|---|
| `app/Services/LangdockAgentService.php` | Synchroner HTTP POST mit Retry |
| `app/Services/StreamingAgentService.php` | Fake-Streaming (char-by-char nach vollem Response) |
| `app/Services/LangdockContextInjector.php` | Context-Injection: RLS-Bootstrap, Phasen-Schemata, User-Daten |
| `app/Services/ChatService.php` | Chat-Nachrichten CRUD |
| `app/Services/PhaseChainService.php` | Auto-Chain P1→P2→P3→P4 via Queue |
| `app/Services/AgentResultStorageService.php` | MD-File-Speicherung (bereits vorhanden) |
| `app/Services/AgentPayloadService.php` | Payload-Aufbau fuer Agent-Calls |
| `app/Services/AgentPromptBuilder.php` | System/User-Prompts fuer Phasen |
| `app/Jobs/ProcessPhaseAgentJob.php` | Asynchroner Worker-Agent-Aufruf |
| `app/Jobs/ProcessChatMessageJob.php` | Chat-Nachrichtenverarbeitung |
| `app/Actions/SendAgentMessage.php` | HTTP-basierte Nachrichtenübermittlung |
| `app/Http/Controllers/McpAgentController.php` | MCP-Routing |
| `app/Http/Controllers/StreamingMcpController.php` | SSE-Endpoint `/mcp/sse` |
| `app/Http/Middleware/VerifyMcpToken.php` | Token-Auth fuer MCP |

---

## 2. Zielarchitektur (SOLL)

### 2.1 Kernprinzipien

1. **Laravel ist Single Source of Truth** fuer alle Userdaten
2. **Langdock bekommt keinen direkten DB-Zugriff** mehr (Issue #137)
3. **Chat-Kommunikation laueft ueber den offiziellen Langdock MCP-Server** (echtes Streaming)
4. **Worker-Agents bleiben reine Worker** — kein Chatfenster, kein User-Kontakt
5. **Agent-Outputs werden als Markdown-Files gespeichert** und per RAG (Ollama) durchsuchbar
6. **User-Isolation** durch Ordnerstruktur + Embedding-Filter

### 2.2 Kommunikationsflow

```
                          ┌──────────────────────────────────────────┐
                          │  Browser (Livewire Chat-Komponente)      │
                          └──────────────┬───────────────────────────┘
                                         │ User-Nachricht
                                         ▼
                          ┌──────────────────────────────────────────┐
                          │  Laravel                                  │
                          │                                           │
                          │  1. ContextProvider laedt aus DB:          │
                          │     - Projekt-Metadaten                   │
                          │     - aktuelle Phase                      │
                          │     - User-Info                           │
                          │                                           │
                          │  2. RAG-Retrieval (RetrieverService):     │
                          │     - Agent-Result-Embeddings             │
                          │     - Paper-Embeddings                    │
                          │     → Top-K Chunks als Kontext            │
                          │                                           │
                          │  3. MCP-Client sendet an Langdock:        │
                          │     - System-Message (Kontext + Chunks)   │
                          │     - User-Message                        │
                          │     → echtes Streaming zurueck             │
                          │                                           │
                          │  4. Response verarbeiten:                  │
                          │     - MD-File speichern                   │
                          │     - Embeddings generieren (async Job)   │
                          │     - chat_messages aktualisieren         │
                          │     - SSE an Browser streamen             │
                          └──────────────────────────────────────────┘
                                         │
                                         ▼
                          ┌──────────────────────────────────────────┐
                          │  Langdock Cloud (MCP-Protokoll)           │
                          │                                           │
                          │  Chat-Agent ("Frontdesk")                 │
                          │  - Beantwortet User-Fragen                │
                          │  - Erklaert Phasen-Ergebnisse              │
                          │  - Signalisiert wenn Worker noetig         │
                          │                                           │
                          │  KEIN direkter DB-Zugriff                 │
                          │  KEIN Aufruf von Worker-Agents            │
                          └──────────────────────────────────────────┘
```

### 2.3 Worker-Agent-Flow (unveraendert, Queue-basiert)

```
Laravel (PhaseChainService / User-Trigger)
  → ProcessPhaseAgentJob dispatched auf Redis Queue
    → LangdockAgentService::call() [API bleibt fuer Worker ok]
      → Agent arbeitet Phase ab
    → AgentResultStorageService speichert MD-File
    → IngestAgentResultJob generiert Embeddings
    → Ergebnisse in DB + als MD verfuegbar fuer RAG
```

Worker-Agents:
- Bekommen Kontext von Laravel (nicht vom Chat-Agent)
- Schreiben Ergebnisse ueber Laravel in die DB
- Werden **nicht** vom Chat-Agent aufgerufen, sondern von Laravel orchestriert

---

## 3. Markdown-RAG-Architektur

### 3.1 Speicherstruktur

```
storage/app/agent-results/
  └── {workspace_id}/
      └── {user_id}/
          └── {projekt_id}/
              ├── P1__scoping_agent__20260408120000.md    ← Phasen-Output
              ├── P2__search_agent__20260408130000.md
              ├── chat__20260408140000.md                  ← Chat-Protokoll
              └── chat__20260408150000.md
```

### 3.2 Zwei Dokumenttypen

| Typ | Dateiname-Muster | Inhalt |
|---|---|---|
| **Phasen-Output** | `P{n}__{agent}__{timestamp}.md` | Strukturiertes Ergebnis einer Phase (wie bisher) |
| **Chat-Protokoll** | `chat__{timestamp}.md` | Konversation User+Assistant, chronologisch, pro Session |

### 3.3 Embedding-Pipeline

```
MD-File gespeichert
  → IngestAgentResultJob (async, Redis Queue)
    → Text in Chunks aufteilen (500 Woerter, 100 Ueberlappung)
    → Fuer jeden Chunk: EmbeddingService::generate() (Ollama nomic-embed-text)
    → INSERT in agent_result_embeddings (pgvector)
      Spalten: id, workspace_id, user_id, projekt_id, chunk_text, embedding, source_file, created_at
```

### 3.4 Retrieval bei Chat-Turn

```
User-Nachricht eingehend
  → Session-Cache pruefen (Redis, TTL 30min)
    → Cache-Hit: gespeicherte Chunks verwenden
    → Cache-Miss:
      → EmbeddingService::generate(user_nachricht)
      → pgvector Similarity Search auf agent_result_embeddings
        WHERE workspace_id = ? AND user_id = ? AND projekt_id = ?
      → Top-K Chunks (k=5-10) in Session-Cache schreiben
  → Chunks als System-Kontext an Chat-Agent mitgeben
```

### 3.5 User-Isolation

| Schicht | Mechanismus |
|---|---|
| **Dateisystem** | Ordner `{workspace_id}/{user_id}/` — physische Trennung |
| **Embeddings (DB)** | WHERE-Filter auf `workspace_id` + `user_id` bei jedem Retrieval |
| **RLS (optional)** | Kann spaeter ergaenzt werden fuer defense-in-depth |

---

## 4. Neue und geaenderte Komponenten

### 4.1 Neu zu erstellen

| Komponente | Datei | Beschreibung |
|---|---|---|
| **LangdockMcpClient** | `app/Services/LangdockMcpClient.php` | MCP-Protokoll-Client fuer den offiziellen Langdock MCP-Server. Echtes Streaming |
| **ContextProvider** | `app/Services/ContextProvider.php` | Ersetzt `LangdockContextInjector`. Laedt Kontext aus DB + RAG-Chunks. Baut System-Message |
| **IngestAgentResultJob** | `app/Jobs/IngestAgentResultJob.php` | Chunking + Embedding fuer Agent-Output-MD-Files |
| **Migration** | `database/migrations/..._create_agent_result_embeddings.php` | pgvector-Tabelle fuer Agent-Result-Embeddings |

### 4.2 Zu aendern

| Komponente | Aenderung |
|---|---|
| `StreamingAgentService` | Ersetzen: Fake-Streaming → echter MCP-Stream vom LangdockMcpClient |
| `AgentResultStorageService` | Erweitern: auch Chat-Protokolle speichern, nach Save automatisch `IngestAgentResultJob` dispatchen |
| `RetrieverService` | Erweitern: auch `agent_result_embeddings` durchsuchen |
| `ChatService` | Chat-Turns zusaetzlich als MD-File persistieren |
| `ProcessChatMessageJob` | Auf MCP-Client umstellen |
| `SendAgentMessage` | Auf MCP-Client umstellen |
| Livewire Chat-Komponente | Echtes SSE-Streaming vom MCP konsumieren |

### 4.3 Zu entfernen / abloesen

| Komponente | Grund |
|---|---|
| `LangdockContextInjector` | Wird durch `ContextProvider` ersetzt. DB-Schema-Injection an Agents entfaellt, weil Agents keinen direkten DB-Zugriff mehr haben |
| Direkter Postgres-MCP-Zugriff fuer Langdock-Agents | Sicherheitsrisiko (Issue #137). Agents arbeiten nur noch ueber Laravel |
| `langdock_agent` DB-User | Wird nicht mehr benoetigt |

---

## 5. Sicherheitsverbesserungen

### 5.1 Postgres-MCP-Endpoint absichern (Issue #137)

**IST:** Langdock-Cloud-Agents greifen direkt auf `/mcp/sse` zu und fuehren SQL via `execute_sql` aus.

**SOLL:** 
- `/mcp/sse` wird **nur** fuer lokale/interne Nutzung freigegeben (z.B. Claude Code)
- Langdock-Cloud-Agents bekommen **keinen** direkten DB-Zugriff mehr
- Worker-Agents schreiben ueber Laravel (PhaseChainService → DB)
- Der `langdock_agent` DB-User wird deaktiviert

### 5.2 Datentrennung

- Userdaten bleiben in Laravel/PostgreSQL
- Langdock bekommt nur den noetigsten Kontext pro Request (Projekt-ID, Forschungsfrage, RAG-Chunks)
- Keine DB-Schemata mehr an Agents leaken (Issue #84, #124)

---

## 6. Migrationsplan

### Phase A: Grundlagen (Woche 1)

- [ ] `agent_result_embeddings` Migration erstellen
- [ ] `IngestAgentResultJob` implementieren
- [ ] `AgentResultStorageService` um Chat-Protokoll-Speicherung erweitern
- [ ] `RetrieverService` um `agent_result_embeddings` erweitern
- [ ] Session-Cache fuer RAG-Chunks (Redis)

### Phase B: MCP-Client (Woche 2)

- [ ] `LangdockMcpClient` implementieren (offizieller Langdock MCP)
- [ ] `ContextProvider` implementieren (ersetzt `LangdockContextInjector`)
- [ ] `StreamingAgentService` auf echtes MCP-Streaming umbauen
- [ ] Livewire Chat-Komponente auf SSE-Streaming umstellen

### Phase C: Absicherung (Woche 3)

- [ ] Postgres-MCP-Endpoint nur noch intern erreichbar machen
- [ ] `langdock_agent` DB-User deaktivieren
- [ ] Worker-Agent-Flow validieren (API bleibt, kein direkter DB-Zugriff)
- [ ] End-to-End-Test: Chat + Worker + RAG

### Phase D: Aufraumen (Woche 4)

- [ ] `LangdockContextInjector` entfernen (nach Umstellung aller Caller)
- [ ] Alte API-basierte Chat-Logik entfernen
- [ ] ARCHITECTURE.md aktualisieren (diese Datei wird zur neuen ARCHITECTURE.md)
- [ ] Monitoring: RAG-Latenz, MCP-Streaming-Performance

---

## 7. Offene Entscheidungen

| Frage | Optionen | Empfehlung |
|---|---|---|
| MCP-Auth-Methode | API-Key / OAuth / MCP-native | Abhaengig von offiziellem Langdock MCP SDK |
| Chat-History-Retention | Unbegrenzt / 30 Tage / pro Projekt | Pro Projekt, loeschbar mit Projekt |
| Worker via API oder MCP | API beibehalten / auch MCP | API beibehalten — kein Streaming noetig fuer Worker |
| Chat-Protokoll-Granularitaet | Pro Session / pro Tag / pro Turn | Pro Session (neue Datei bei >30min Inaktivitaet) |

---

## 8. Verwandte Issues

| Issue | Thema | Bezug |
|---|---|---|
| #137 | Offener Postgres-MCP-Endpoint | Wird durch diese Architektur geloest (kein direkter DB-Zugriff mehr) |
| #136 | Master-Agent-Architektur | Chat-Agent als Frontdesk entspricht dem Master-Agent-Konzept |
| #124 | Agent interpretiert `app.` als Schema | Entfaellt — Agents bekommen keine DB-Schemata mehr |
| #91 | ContextInjector uebergibt Kontext nicht vollstaendig | Wird durch ContextProvider + RAG ersetzt |
| #84 | Agents kennen echte Tabellennamen nicht | Entfaellt — Agents schreiben nicht mehr direkt in DB |
| #58 | Kein echtes Streaming | Wird durch MCP-Streaming geloest |
| #121 | MCP-Controller-Routing | Muss an neue Architektur angepasst werden |

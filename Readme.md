# Linn.Games — Laravel Application

> **app.linn.games** — Research-Management-Plattform mit KI-gestützter systematischer Literaturrecherche (8-Phasen Systematic Review).

## Quick Start (Local Development)

**Vollständige Anleitung:** [`.github/instructions/local-dev-setup.md`](.github/instructions/local-dev-setup.md)

```bash
composer setup
docker compose up -d
# Öffne http://localhost:6481 → Login: editor@test.local / password
```

---

## Stack

| Komponente | Version |
|---|---|
| Laravel | 12 |
| PHP | 8.4 |
| Livewire / Volt | 1.7 (Inline-Komponenten) |
| Filament | 4.9 (Admin-Panel) |
| Fortify | 1.30 (Auth + 2FA) |
| Tailwind CSS | 4 (via Vite) |
| PostgreSQL | 16 + pgvector |
| Redis | Alpine (Cache, Session, Queue) |
| MayringCoder | Python / FastAPI (separater Stack) |

---

## Architektur

```
Internet
  │
  ├─ app.linn.games:6481 ──▶ nginx ──▶ php-fpm (Laravel 12)
  │                                  ├─▶ queue-worker (Phase Agents)
  │                                  ├─▶ postgres:5432 + pgvector
  │                                  ├─▶ redis (Cache/Queue/Session)
  │                                  ├─▶ mcp-paper-search:8089 (/paper-mcp/)
  │                                  └─▶ three.linn.games (Ollama, keine GPU auf u-server)
  │
  └─ mcp.linn.games:6480 ──▶ mayring-nginx ──▶ mayring-api:8090   (REST + /wiki /ambient)
                                             ├─▶ mayring-mcp:8092  (MCP/SSE)
                                             ├─▶ mayring-webui:7860 (Gradio /ui/)
                                             └─▶ mayring-watcher   (Conversation-Ingest)

Netzwerke: mayring-internal (MayringCoder intern) + linn-shared (Stack-übergreifend)
```

**Wichtig:** Ollama läuft dediziert auf `three.linn.games` — NICHT als Docker-Service auf u-server (kein GPU).

---

## Features

### Authentifizierung & Benutzerverwaltung
- Login, Registrierung, Passwort-Reset (Fortify)
- Zwei-Faktor-Authentifizierung (TOTP + Recovery-Codes)
- DSGVO-Datenexport & Account-Löschung (`/dsgvo/export`, `/dsgvo/delete-account`)

### Systematische Literaturrecherche (8 Phasen)
- Forschungsfrage eingeben → KI-Agents (Claude API) laufen auto-chained P1→P4, P5→P8
- 32 Eloquent-Models für die 8-Phasen-Pipeline
- Vektor-Embeddings: `paper_embeddings` + pgvector (IVFFlat-Index) via Ollama
- Screening, Qualitätsbewertung (RoB2/CASP), Synthese, Dokumentation

### BYO LLM-Provider
- Eigener Anthropic-API-Key oder OpenAI-kompatibler Endpoint (Ollama, etc.)
- Konfigurierbar pro User unter `/settings/ai-model`
- `User::resolvedChatModel()` — Platform-Default oder User-Key (encrypted at rest)
- Whitelist in `config/services.anthropic.available_chat_models`
- Billing-Skip bei non-platform Providern

### MayringCoder-Integration
- Phase 7 (Codierung/Synthese) nutzt MayringCoder via `MayringMcpClient`
- JWT RS256-Auth: `/api/mayring/token-exchange` → 30-Tage Service-JWT für Watcher-Daemon
- Refresh-Token: `POST /api/mayring/refresh-token` mit 7-Tage Sliding-Session-Grace

### Admin-Panel (Filament)
- `ContactResource` — Kontaktanfragen
- `UserResource` — Benutzerverwaltung, Status-Flow, Kredit-Verwaltung
- Rollen & Berechtigungen (Spatie Permission)

### Credit-System
- `CreditService::deduct()` bucht Token-Kosten pro Agent-Call
- `CreditTransaction` loggt Verbrauch mit Agent-Key + Token-Count
- `WorkspaceLowBalance` Event bei <10% → Admin-Notification
- Exceptions: `InsufficientCreditsException`, `AgentDailyLimitExceededException`

### CI/CD
- `deploy.sh` — Vollständiges Deployment (Build, Migrate, Cache, Start)
- `deploy-mayring.sh` — Separates MayringCoder-Stack-Deployment
- Deploy-Benachrichtigung per E-Mail + GitHub Issues bei Fehler

---

## KI-Agent Flow

```
UI (Volt/Livewire)
  → TriggersPhaseAgent trait
  → ProcessPhaseAgentJob (Queue, async)
  → ClaudeService::callByConfigKey()
      ├─ PromptLoaderService (resources/prompts/agents/*.md + Skills)
      ├─ ClaudeContextBuilder::build() (Projektdaten als Markdown in System-Prompt)
      └─ HTTP POST api.anthropic.com/v1/messages
           └─ [Tool-Use-Loop für P7 via MayringMcpClient]
  → AgentPayloadService::persistPayload() (JSON Envelope → DB)
  → PhaseChainService::maybeDispatchNext() (auto-chain P1→P4, P5→P8)
```

---

## 8-Phasen Systematic Review

| Phase | Beschreibung | Agent-Config-Key |
|-------|-------------|-----------------|
| P1 | PICO/SPIDER/PEO-Komponenten-Analyse | `scoping_mapping_agent` |
| P2 | Review-Typ & Scope-Bestimmung | `scoping_mapping_agent` |
| P3 | Datenbankauswahl + Suchmethodik | `scoping_mapping_agent` |
| P4 | Suchstrings generieren (Boolean) | `search_agent` |
| P5 | Titel/Abstract-Screening (L1) | `review_agent` |
| P6 | Qualitätsbewertung RoB2/CASP (L2) | `review_agent` |
| P7 | Datenextraktion & Mayring-Codierung | `review_agent` + MayringMCP |
| P8 | Synthese + PRISMA-Dokumentation | `review_agent` |

**Auto-Chain:** P1→P4 und P5→P8 laufen automatisch durch.  
**Pause bei P4→P5:** Manueller Paper-Import (CSV/DOI) erforderlich.

---

## Zentrale Services

| Service | Datei | Aufgabe |
|---------|-------|---------|
| `ClaudeService` | `app/Services/ClaudeService.php` | Claude API, Retry, Tool-Use-Loop, Token-Billing |
| `PromptLoaderService` | `app/Services/PromptLoaderService.php` | Agent-Prompts aus `.md` + Skill-Includes (YAML-Frontmatter) |
| `ClaudeContextBuilder` | `app/Services/ClaudeContextBuilder.php` | Strukturierter Projekt-Kontext P1–P6 für System-Prompt |
| `PhaseChainService` | `app/Services/PhaseChainService.php` | Auto-Chain-Orchestrierung zwischen Phasen |
| `CreditService` | `app/Services/CreditService.php` | Workspace-Guthaben, Token→Cent (Input/Output getrennt) |
| `RetrieverService` | `app/Services/RetrieverService.php` | Semantische Suche via pgvector + Ollama |
| `AgentPayloadService` | `app/Services/AgentPayloadService.php` | JSON Envelope v1 parsen → Phasen-Tabellen |
| `MayringMcpClient` | `app/Services/MayringMcpClient.php` | HTTP-Client zu MayringCoder (Tool-Use P7) |
| `StreamingAgentService` | `app/Services/StreamingAgentService.php` | SSE-Streaming für Dashboard-Chat |

---

## MayringCoder Stack (`docker-compose.mayring.yml`)

Separater Python/FastAPI-Stack auf `mcp.linn.games:6480`.

| Service | Port | Funktion |
|---------|------|---------|
| `mayring-nginx` | 6480 | Reverse Proxy, routet REST→api, MCP→mcp, UI→webui |
| `mayring-api` | 8090 | REST API: Ingestion, Wiki, Ambient, Predictive, RAG |
| `mayring-mcp` | 8092 | MCP/SSE-Server (OAuth, Tool-Use) |
| `mayring-webui` | 7860 | Gradio Dashboard (`/ui/`) |
| `mayring-pi` | — | Pi-Agent (interne Analyse) |
| `mayring-watcher` | — | Claude-Conversation-Ingest (Profil `watcher`) |

### Conversation Watcher

Überwacht `~/.claude/projects` inkrementell und ingested neue Turns automatisch via `POST /conversation/micro-batch` in Workspace `system`.

```bash
# Aktivierung: CLAUDE_PROJECTS_DIR setzen
CLAUDE_PROJECTS_DIR=~/.claude/projects ./deploy-mayring.sh
```

**Routing:** Watcher → `http://mayring-nginx:80` (internes Docker-Netz) → nginx routet `/conversation/*` zu `mayring-api:8090`.

### Deployment

```bash
# Nur MayringCoder Stack
./deploy-mayring.sh

# Mit Watcher
CLAUDE_PROJECTS_DIR=~/.claude/projects ./deploy-mayring.sh
```

**CI:** Automatisch via `deploy-mayring.yml` bei Änderungen an `docker-compose.mayring.yml` oder `docker/mayring/**`.

---

## Benchmarking

Drei Messebenen:

### 1. Retrieval-Qualität (MayringCoder, bereits gebaut)

```bash
# Retrieval-Benchmark (MRR + Recall@K) via API
curl -X POST https://mcp.linn.games/benchmark \
  -H "Authorization: Bearer $MCP_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"top_k": 5, "repo": "org/repo"}'

# Direkt lokal
python src/benchmark_retrieval.py \
  --queries benchmarks/retrieval_queries.yaml --top-k 10
```

Metriken: **MRR** (Mean Reciprocal Rank) + **Recall@K** — misst Qualität der Hybrid-Suche (ChromaDB + SQLite FTS).

### 2. API-Latenz (nginx + Laravel)

```bash
# hey installieren: go install github.com/rakyll/hey@latest
hey -n 100 -c 10 -H "Authorization: Bearer $TOKEN" \
  http://localhost:6480/health

hey -n 50 -c 5 -m POST \
  -H "Authorization: Bearer $MCP_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"turns":[...],"session_id":"bench","workspace_slug":"test"}' \
  http://localhost:6480/conversation/micro-batch
```

### 3. Agent-Pipeline-Durchsatz (P1→P8 Timing)

Phase-Zeiten stehen in `phase_agent_results.created_at` / `updated_at` pro Phase — SQL-Query genügt:

```sql
SELECT
  p.id,
  MIN(par.created_at) AS pipeline_start,
  MAX(par.updated_at) AS pipeline_end,
  EXTRACT(EPOCH FROM (MAX(par.updated_at) - MIN(par.created_at))) AS elapsed_s
FROM projekte p
JOIN phase_agent_results par ON par.projekt_id = p.id
GROUP BY p.id
ORDER BY pipeline_start DESC;
```

---

## Routes

### Web (`routes/web.php`)
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/` | Startseite |
| POST | `/contact` | Kontaktformular |
| GET | `/dashboard` | Dashboard + Chat (auth) |
| GET | `/settings/*` | Profil, Passwort, 2FA, KI-Modell (auth) |
| GET | `/recherche` | Recherche-Übersicht (auth) |
| GET | `/recherche/{projekt}` | Projekt-Detail + Phasen (auth) |
| GET | `/dsgvo/export` | DSGVO-Datenexport (auth) |
| DELETE | `/dsgvo/delete-account` | Account-Löschung (auth) |

### API (`routes/api.php`)
| Methode | Pfad | Auth | Beschreibung |
|---|---|---|---|
| GET | `/api/user` | Sanctum | Authentifizierter Benutzer |
| POST | `/api/papers/ingest` | MCP-Token | Paper-Ingestion |
| GET | `/api/papers/rag-search` | MCP-Token | Vektor-Suche |
| POST | `/api/mayring/token-exchange` | JWT | 30-Tage Service-JWT für Watcher |
| POST | `/api/mayring/refresh-token` | JWT | Token-Refresh (7-Tage Grace) |

---

## Datenbank

**21 Migrationen** — Users, Cache, Jobs, 2FA, Contacts, PageViews, Permissions, Consents, Recherche P1–P8, Indices.

**36+ Models:**
- Core: `User`, `Workspace`, `Contact`, `PageView`, `Consent`, `ChatMessage`
- Recherche: `Projekt`, `Phase`, `P5Treffer` + Phasen-Models P1–P8
- Embeddings: `PaperEmbedding` (pgvector)
- Credits: `CreditTransaction`

**ER-Diagramm:** [ZieldiagramDB.mermaid](ZieldiagramDB.mermaid)

---

## Konfiguration (`.env`)

| Variable | Beschreibung |
|---|---|
| `APP_URL` | `https://app.linn.games` |
| `DB_CONNECTION` | `pgsql` (PostgreSQL 16) |
| `QUEUE_CONNECTION` | `redis` |
| `ANTHROPIC_API_KEY` | Claude API Key (Platform-Default) |
| `MCP_AUTH_TOKEN` | Token für Postgres-MCP-Endpoint |
| `MCP_SERVICE_TOKEN` | Token für MayringCoder Service-Calls |
| `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` | RS256-Schlüsselpaar für Mayring-JWT |
| `OLLAMA_URL` | `https://three.linn.games/ollama` (kein Docker) |
| `MAYRING_MCP_ENDPOINT` | `https://mcp.linn.games/sse` |
| `APP_PORT` | `6481` (Main-Stack), MayringCoder: `MAYRING_PORT=6480` |

---

## Lokale Entwicklung

```bash
composer setup     # Install, Key, Migrate, NPM Build
composer dev       # Server + Queue + Vite (parallel)
composer test      # PHPUnit/Pest Tests
```

## Deployment (Produktion)

```bash
# Main Stack
./deploy.sh
./deploy.sh --skip-build
./deploy.sh --skip-migrate

# MayringCoder Stack
./deploy-mayring.sh
CLAUDE_PROJECTS_DIR=~/.claude/projects ./deploy-mayring.sh
```

---

## Tests

```bash
docker compose run --rm php-test vendor/bin/pest
```

**440+ Tests** — Unit, Auth, Kontakt, Dashboard-Chat, Recherche P1–P8, ProjektPolicy, Agent-Integration, Einladungssystem, Credit-System, BYO-LLM.

| Testsuite | Abdeckung |
|---|---|
| Auth (Login, Register, 2FA, Passwort) | ✅ |
| Kontaktformular | ✅ |
| Dashboard-Chat (Multi-Turn, Streaming, Fehler) | ✅ |
| Recherche (Projekt CRUD, Zugriff, Policy) | ✅ |
| Agent-Buttons P1–P8 + Auto-Chain | ✅ |
| Credit-System (Deduct, Limits, Low-Balance) | ✅ |
| BYO-LLM (Key-Routing, Billing-Skip) | ✅ |

---

## MCP-Endpunkte

| Endpunkt | Beschreibung | Doku |
|---|---|---|
| `/mcp/sse` | PostgreSQL MCP (Entwickler-DB-Zugriff) | [docs/API.md](docs/API.md) |
| `/paper-mcp/sse` | Paper-Search MCP | [docs/API.md](docs/API.md) |
| `mcp.linn.games/sse` | MayringCoder MCP (Tool-Use) | [docs/API.md](docs/API.md) |
| `mcp.linn.games/conversation/micro-batch` | Conversation-Ingestion | POST, Bearer |
| `mcp.linn.games/benchmark` | Retrieval-Benchmark | POST, Bearer |
| `mcp.linn.games/wiki/generate` | Wiki-Generierung | POST, Bearer |

**Auth:** Bearer Token, X-API-Key oder `?token=` Query-Parameter.

👉 **Vollständige API-Dokumentation:** [docs/API.md](docs/API.md)

---

## Benutzerverwaltung

```
Selbst-Registrierung → waitlisted → [Admin] → trial → [Admin] → active
Admin-Einladung      → invited    → [User]  → trial → [Admin] → active
```

---

## Contributing

Siehe [CONTRIBUTING.md](CONTRIBUTING.md).

## Lizenz

Proprietär — Alle Rechte vorbehalten.

# Linn.Games — Laravel Application

> **app.linn.games** — Research-Management-Plattform mit KI-gestützter systematischer Literaturrecherche.

## 🚀 Quick Start (Local Development)

**Siehe: [`.github/instructions/local-dev-setup.md`](.github/instructions/local-dev-setup.md)** für vollständige lokale Entwicklungs-Anleitung.

**TL;DR:**
```bash
composer setup
docker compose up -d
# Öffne http://localhost:6480 → Login: editor@test.local / password
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
| PostgreSQL | 16 (UUID-Primärschlüssel) |
| Redis | Alpine (Cache, Session, Queue) |

## Architektur

```
┌──────────────┐     ┌───────────┐     ┌──────────┐
│    Nginx     │────▸│  PHP-FPM  │────▸│ Postgres │
│  (Port 6481) │     └───────────┘     │ + pgvector│
└──────────────┘     ┌───────────┐     └──────────┘
                     │Queue-Worker│────▸┌──────────┐
                     └───────────┘     │  Redis   │
                     ┌────────────────┐└──────────┘
                     │ Postgres-MCP   │  (KI-Datenbankzugriff via SSE)
                     │ /mcp/sse       │
                     └────────────────┘
                     ┌────────────────┐
                     │ Paper-Search   │  (Literatursuche + Embedding)
                     │ /paper-mcp/    │
                     └────────────────┘
                     ┌────────────────┐
                     │   Ollama       │  (Embedding-Modell: nomic-embed-text)
                     │ /ollama/       │
                     └────────────────┘
```

**Docker-Services:** `web` (nginx), `php-fpm`, `php-cli`, `php-test`, `queue-worker`, `postgres`, `redis`, `postgres-mcp`, `ollama`, `mcp-paper-search`

## Features

### Authentifizierung & Benutzerverwaltung
- Login, Registrierung, Passwort-Reset (Fortify, plain Blade)
- Zwei-Faktor-Authentifizierung (TOTP + Recovery-Codes)
- E-Mail-Verifizierung
- Profil-Einstellungen (Volt Inline-Komponenten)
- DSGVO-Datenexport & Account-Löschung (`/dsgvo/export`, `/dsgvo/delete-account`)

### Systematische Literaturrecherche
- **ResearchInput** — Forschungsfrage eingeben → Projekt erstellen → KI-Agent (Claude API) via Queue auslösen
- **ProjektListe** — Alle Recherche-Projekte des Benutzers (sortiert nach Erstellungsdatum)
- **ProjektDetail** — Einzelprojekt mit Phasen (P1–P8) und Trefferliste (P5)
- 32 Eloquent-Models für 8 Phasen eines systematischen Reviews
- KI-Integration: `ClaudeService` → Anthropic Claude API (direkte HTTP-Aufrufe, testbar via `Http::fake()`)
- Vektor-Embeddings: `paper_embeddings`-Tabelle mit pgvector (IVFFlat-Index)
- Activity-Logging: Änderungen an `Projekt` und `User` via `spatie/laravel-activitylog`

### Admin-Panel (Filament)
- `ContactResource` — Kontaktanfragen verwalten
- `UserResource` — Benutzerverwaltung
- Rollen & Berechtigungen (Spatie Permission)

### Kontaktformular
- Formular-Submission mit Rate-Limiting
- Bestätigungs-E-Mail an Absender (`ContactConfirmationMail`)
- Benachrichtigung an Team (`ContactInquiryMail`)
- Seitentracking-Middleware (`TrackPageView`)

### CI/CD & Deployment
- `deploy.sh` — Automatisiertes Deployment (Build, Migrate, Cache, Start)
- Deploy-Benachrichtigung per E-Mail (`DeployNotificationMail`)
- Optionen: `--skip-build`, `--skip-migrate`

### MCP PostgreSQL Endpoint
- Entwickler-Datenbankzugriff via SSE (`/mcp/sse`) — für Claude Code und ähnliche MCP-Clients
- Authentifizierung: Bearer-Token, X-API-Key oder Query-Parameter (alle Endpunkte inkl. `/messages/`)
- Eingeschränkter DB-Benutzer — kein Schreibzugriff auf produktive Tabellen

### Ollama Embedding Endpoint
- Lokales Embedding-Modell (`nomic-embed-text`) via Ollama
- Nginx-Proxy unter `/ollama/` mit Token-Authentifizierung
- Genutzt für Vektor-Suche in Recherche-Treffern und Agent-Outputs

### Paper-Search MCP
- Literatursuche und Paper-Ingestion via MCP-Protokoll (`/paper-mcp/`)
- Automatischer Paper-Download und Embedding-Erzeugung

## Service-Architektur

### KI-Agent Flow

```
UI (Volt/Livewire)
  → TriggersPhaseAgent trait | agent-action-button.blade.php
  → SendAgentMessage::execute()
  → ClaudeService::callByConfigKey()
      ├─ PromptLoaderService (lädt .md aus resources/prompts/agents/ + Skills)
      ├─ ClaudeContextBuilder::build() (Projektdaten als Markdown in System-Prompt)
      └─ HTTP POST api.anthropic.com/v1/messages
  → AgentPayloadService::persistPayload() (JSON Envelope → DB)
  → LangdockArtifactService::persistFromAgentResponse() (Markdown-Files)
  → PhaseAgentResult gespeichert
  → PhaseChainService::maybeDispatchNext() (auto-chain P1→P4, P5→P8)
```

**Async:** `ProcessPhaseAgentJob` (Queue) für alle Phasen-Agents.

### Zentrale Services

| Service | Datei | Aufgabe |
|---------|-------|---------|
| `ClaudeService` | `app/Services/ClaudeService.php` | Ruft Claude API auf, Retry-Logik, Tool-Use-Loop (Mayring), Token-Abrechnung |
| `PromptLoaderService` | `app/Services/PromptLoaderService.php` | Lädt Agent-Prompts aus `.md`-Dateien inkl. Skill-Includes via YAML-Frontmatter |
| `ClaudeContextBuilder` | `app/Services/ClaudeContextBuilder.php` | Baut strukturierten Markdown-Kontext aus Projektdaten (P1–P6) für den System-Prompt |
| `PhaseChainService` | `app/Services/PhaseChainService.php` | Orchestriert Auto-Chain zwischen Phasen nach Agent-Abschluss |
| `CreditService` | `app/Services/CreditService.php` | Workspace-Guthaben, Token→Cent-Umrechnung (Input/Output getrennt), Tageslimits |
| `RetrieverService` | `app/Services/RetrieverService.php` | Semantische Suche via pgvector (Ollama Embeddings) |
| `AgentPayloadService` | `app/Services/AgentPayloadService.php` | Parst JSON Envelope v1 und schreibt in Phasen-Tabellen |
| `MayringMcpClient` | `app/Services/MayringMcpClient.php` | HTTP-Client zum MayringCoder-Service (Tool-Use-API für P7) |
| `StreamingAgentService` | `app/Services/StreamingAgentService.php` | SSE-Streaming für Dashboard-Chat |

### 8-Phasen Systematic Review

| Phase | Beschreibung | Agent-Config-Key |
|-------|-------------|-----------------|
| P1 | PICO/SPIDER/PEO-Komponenten | `scoping_mapping_agent` |
| P2 | Review-Typ & Scoping | `scoping_mapping_agent` |
| P3 | Datenbankauswahl | `scoping_mapping_agent` |
| P4 | Suchstrings generieren | `search_agent` |
| P5 | Screening (L1/L2) | `review_agent` |
| P6 | Qualitätsbewertung (RoB2/CASP) | `review_agent` |
| P7 | Datenextraktion & Synthese | `review_agent` |
| P8 | Dokumentation & Abschluss | `review_agent` |

**P4→P5:** Kein Auto-Chain (manueller Paper-Import über CSV/DOI nötig).

### Credit-System

- `CreditService::deduct()` bucht Token-Kosten pro Agent-Call
- Preise konfigurierbar via `config/services.php` (`anthropic.price_per_1k_input_tokens_cents`)
- `CreditTransaction` loggt jeden Verbrauch mit Agent-Key und Token-Count
- Exceptions: `InsufficientCreditsException`, `AgentDailyLimitExceededException`

### Benutzerverwaltung & Beta

User-Status-Flow:
```
Selbst-Registrierung → waitlisted → [Admin genehmigt] → trial → [Admin aktiviert] → active
Admin-Einladung     → invited    → [User akzeptiert]  → trial → [Admin aktiviert] → active
```

---

## Routes

### Web (`routes/web.php`)
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/` | Startseite |
| POST | `/contact` | Kontaktformular |
| GET | `/Impressum.html` | Impressum |
| GET | `/dsgvo.html` | Datenschutzerklärung |
| GET | `/AGB.html` | AGB |
| GET | `/dashboard` | Dashboard (auth) |
| GET | `/settings/*` | Profil, Passwort, Appearance, 2FA (auth) |
| GET | `/recherche` | Recherche-Übersicht (auth) |
| GET | `/recherche/{projekt}` | Projekt-Detail (auth) |
| GET | `/dsgvo/export` | DSGVO-Datenexport (auth) |
| DELETE | `/dsgvo/delete-account` | Account-Löschung (auth) |

### API (`routes/api.php`)
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/user` | Authentifizierter Benutzer (Sanctum) |
| POST | `/api/papers/ingest` | Paper-Ingestion (MCP-Token) |
| GET | `/api/papers/rag-search` | Vektor-Suche (MCP-Token) |

## Datenbank

**21 Migrationen** — Users, Cache, Jobs, 2FA, Contacts, PageViews, Permissions, Consents, Recherche P1–P8, Indices, Prisma-Funktion.

**36+ Models:**
- Core: `User`, `Contact`, `PageView`, `Consent`, `ChatMessage`
- Recherche: `Projekt`, `Phase`, `P5Treffer` + 29 Phasen-Models (P1–P8)
- Embeddings: `PaperEmbedding` (pgvector)

## Lokale Entwicklung

```bash
# Composer-Scripts
composer setup     # Install, Key, Migrate, NPM Build
composer dev       # Server + Queue + Vite (parallel)
composer test      # PHPUnit/Pest Tests
```

## Deployment (Produktion)

```bash
./deploy.sh                    # Vollständiges Deployment
./deploy.sh --skip-build       # Ohne Docker-Rebuild
./deploy.sh --skip-migrate     # Ohne Migrationen
```

**Ablauf:** Pull Images → Build → Start Postgres/Redis → Migrate → Cache Config/Routes/Views → Start All → Deploy-Mail

## Konfiguration

Umgebungsvariablen in `.env`:

| Variable | Beschreibung |
|---|---|
| `APP_URL` | `https://app.linn.games` |
| `DB_CONNECTION` | `pgsql` (PostgreSQL 16) |
| `QUEUE_CONNECTION` | `redis` |
| `CACHE_STORE` | `redis` |
| `SESSION_DRIVER` | `redis` |
| `MAIL_HOST` | SMTP (Strato) |
| `ANTHROPIC_API_KEY` | Anthropic Claude API Key (KI-Agent-Aufrufe) |
| `MCP_AUTH_TOKEN` | Token für MCP-PostgreSQL-Endpoint |

## Tests

```bash
# Via Docker (PostgreSQL)
docker compose run --rm php-test vendor/bin/pest
```

**440+ Tests** — Unit, Auth, Kontakt, Dashboard-Chat, Recherche P1–P8, ProjektPolicy, Agent-Integration, Einladungssystem.

| Testsuite | Abdeckung |
|---|---|
| Auth (Login, Register, 2FA, Passwort) | ✅ |
| Kontaktformular (Validierung, Submission) | ✅ |
| Dashboard-Chat (Agent API, Multi-Turn, Fehler) | ✅ |
| Recherche (Projekt erstellen, Liste, Detail, Zugriff) | ✅ |
| ProjektPolicy (Owner-Zugriff CRUD) | ✅ |
| Agent-Buttons (P1–P7 Phasen) | ✅ |

## Dokumentation & API

MCP-Endpunkte und API:

| Endpunkt | Beschreibung | Doku |
|----------|-------------|------|
| `/mcp/sse` & `/messages/` | PostgreSQL MCP (Entwickler-Datenbankzugriff) | [docs/API.md](docs/API.md#-postgresql-mcp-endpoint) |
| `/paper-mcp/sse` & `/paper-messages/` | Paper-Search MCP für Literatursuche | [docs/API.md](docs/API.md#-paper-search-mcp-endpoint) |
| `/ollama/*` | Embedding-Proxy (nomic-embed-text) | [docs/API.md](docs/API.md#-ollama-embedding-endpoint) |
| `/api/papers/ingest` | Paper-Ingestion (MCP-Token) | [docs/API.md](docs/API.md) |
| `/api/papers/rag-search` | Vektor-Suche (MCP-Token) | [docs/API.md](docs/API.md) |

**Alle MCP-Endpunkte nutzen:** Bearer Token, X-API-Key oder Query-Parameter (`?token=...`)

👉 **Vollständige API-Dokumentation:** [docs/API.md](docs/API.md)

## Datenbank-Diagramm

📊 **ER-Diagramm:** [ZieldiagramDB.mermaid](ZieldiagramDB.mermaid) — Klick auf die Datei um das Diagram anzuschauen

Alle 46 Tabellen des Projekts mit ihren Spalten, Datentypen und Constraints. Die Beziehungen auf einen Blick:

# Beziehungstypen im Überblick:

Beziehung	Typ	Erklärung
users ↔ workspaces	M:M (via workspace_users)	Ein Nutzer kann in mehreren Arbeitsbereichen sein, ein Arbeitsbereich hat mehrere Nutzer
workspaces → projekte	1:M	Ein Arbeitsbereich hat viele Projekte
projekte → phasen	1:M (max 8)	Jedes Projekt hat bis zu 8 Phasen, eindeutig per (projekt_id, phase_nr)
projekte → p1–p8 Tabellen	1:M	Jede Phase hat mehrere Ergebnis-Datensätze pro Projekt
p5_treffer → p5_screening / p6 / p7	1:M	Ein Treffer (Paper) wird mehrfach bewertet, gescreent, extrahiert
p5_treffer → p5_treffer	Self-Ref	Duplikat-Erkennung (duplikat_von verweist auf Original)
p4_suchstrings → p4_anpassungsprotokoll	1:M	Ein Suchstring hat mehrere Versionen/Änderungen
p4_suchstrings → p8_suchprotokoll	1:M	Suchprotokolle referenzieren die Original-Suchstrings
paper_embeddings ↔ chunk_codierungen	1:1	Jeder Embedding-Chunk wird genau einmal codiert (Mayring)
workspaces → credit_transactions	1:M	Alle Buchungen (Aufladungen + Verbrauch) pro Workspace

# Kaskadenverhalten:

    Fast alles löscht kaskadierend (ON DELETE CASCADE), wenn das übergeordnete Objekt gelöscht wird
    Ausnahmen: papers und paper_embeddings setzen projekt_id auf NULL (ON DELETE SET NULL), damit importierte Daten nicht verloren gehen
    workspaces.owner_id ist ebenfalls SET NULL – ein Workspace überlebt, wenn der Ersteller gelöscht wird

## Contributing

Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für Branch-Konventionen, Merge-Fluss und Arbeitsablauf.

## Lizenz

Proprietär — Alle Rechte vorbehalten.

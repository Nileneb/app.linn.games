# Linn.Games — Laravel Application

> **app.linn.games** — Research-Management-Plattform mit KI-gestützter systematischer Literaturrecherche.

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
- **ResearchInput** — Forschungsfrage eingeben → Projekt erstellen → Langdock-KI-Agent via Queue auslösen
- **ProjektListe** — Alle Recherche-Projekte des Benutzers (sortiert nach Erstellungsdatum)
- **ProjektDetail** — Einzelprojekt mit Phasen (P1–P8) und Trefferliste (P5)
- 32 Eloquent-Models für 8 Phasen eines systematischen Reviews
- KI-Integration: `LangdockAgentService` → Langdock Agents Completions API (phasenweise Agent-Calls)
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
- Langdock-KI-Zugriff auf die Datenbank via SSE (`/mcp/sse`)
- Authentifizierung: Bearer-Token, X-API-Key oder Query-Parameter
- Eingeschränkter DB-Benutzer (`langdock_agent`)

### Ollama Embedding Endpoint
- Lokales Embedding-Modell (`nomic-embed-text`) via Ollama
- Nginx-Proxy unter `/ollama/` mit Token-Authentifizierung
- Genutzt von Langdock für Vektor-Suche in Recherche-Treffern

### Paper-Search MCP
- Literatursuche und Paper-Ingestion via MCP-Protokoll (`/paper-mcp/`)
- Automatischer Paper-Download und Embedding-Erzeugung

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
| `MCP_AUTH_TOKEN` | Token für MCP-PostgreSQL-Endpoint |
| `LANGDOCK_DB_*` | Eingeschränkter DB-Benutzer für KI-Agent |

## Tests

```bash
# Via Docker (PostgreSQL)
docker compose run --rm php-test vendor/bin/pest
```

**247 Tests** — Unit, Auth, Kontakt, Dashboard-Chat, Recherche P1–P8, ProjektPolicy, Agent-Integration.

| Testsuite | Abdeckung |
|---|---|
| Auth (Login, Register, 2FA, Passwort) | ✅ |
| Kontaktformular (Validierung, Submission) | ✅ |
| Dashboard-Chat (Agent API, Multi-Turn, Fehler) | ✅ |
| Recherche (Projekt erstellen, Liste, Detail, Zugriff) | ✅ |
| ProjektPolicy (Owner-Zugriff CRUD) | ✅ |
| Agent-Buttons (P1–P7 Phasen) | ✅ |

## Dokumentation & API

Externe Systeme (Langdock, MCP-Server) nutzen die folgenden Endpunkte:

| Endpunkt | Beschreibung | Doku |
|----------|-------------|------|
| `/mcp/sse` & `/messages/` | PostgreSQL MCP für KI-Datenbankzugriff | [docs/API.md](docs/API.md#-postgresql-mcp-endpoint) |
| `/paper-mcp/sse` & `/paper-messages/` | Paper-Search MCP für Literatursuche | [docs/API.md](docs/API.md#-paper-search-mcp-endpoint) |
| `/ollama/*` | Embedding-Proxy (Langdock) | [docs/API.md](docs/API.md#-ollama-embedding-endpoint) |
| `/api/webhooks/langdock` | Webhook für Phase-Ergebnisse & Chat-Responses | [docs/API.md](docs/API.md#-langdock-webhook-endpoint) |
| `/api/user` | Sanctum User-Endpunkt (Authentifizierung) | [docs/API.md](docs/API.md#-sanctum-user-endpoint) |

**Alle MCP-Endpunkte nutzen:** Bearer Token, X-API-Key oder Query-Parameter (`?token=...`)

👉 **Vollständige API-Dokumentation:** [docs/API.md](docs/API.md)

## Contributing

Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für Branch-Konventionen, Merge-Fluss und Arbeitsablauf.

## Lizenz

Proprietär — Alle Rechte vorbehalten.

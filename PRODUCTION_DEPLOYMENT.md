# MayringCoder — Production Deployment Plan

> **Stand:** April 2026  
> **Kontext:** app.linn.games (Laravel 12) nutzt MayringCoder als Backend für qualitative Inhaltsanalyse.

---

## Erkenntnisse: Modell-Strategie

### Problem
`mayring-qwen3:2b` (lokal, dieses Repo) ist ein **Custom-Modelfile** auf Basis von Qwen3 — optimiert für **Code-Analyse** (security reviews, dependency checks). Das Modell ist biased für Code-Reasoning, nicht für qualitative Forschungs-Kategorisierung.

### Entscheidung

| Umgebung | Modell | Begründung |
|----------|--------|-----------|
| **Lokaler PC** (Dev/Code-Review) | `mayring-qwen3:2b` | Code-Analyse, bleibt unverändert |
| **Production-Server** (app.linn.games) | `qwen3.5:2b` (Qwen3 Instruct, allgemein) | Kein Code-Bias, hat Tool-Use, eignet sich für qualitative Forschungskategorisierung |

**Langfristig:** Fine-Tuning-Daten aus Production-Runs sammeln → eigenes `mayring-research:2b` Modell trainieren.

---

## Bisherige Optimierungen (bereits implementiert)

### Weg 1 — Anthropic Prompt-Caching im Tool-Use-Loop

**Problem:** `ClaudeService::callWithToolUse()` schickte bei jeder Tool-Use-Iteration den vollen System-Prompt neu → dominanter Cost-Treiber (`input_cache_write_5m` laut API-CSV).

**Fix (in `app/Services/ClaudeService.php`):**
```php
'system' => [['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]],
// + Header:
'anthropic-beta' => 'prompt-caching-2024-07-31',
```

**Ergebnis:** Iteration 1 = `cache_write` ($1.00/M input), Iteration 2+ = `cache_read` ($0.08/M input) → **92% günstiger** bei Folgeschritten.

---

### Weg 2 — Pi/Ollama-Routing (zero Anthropic-Kosten)

**Fix (in `app/Services/ClaudeService.php`):**
```php
if ($configKey === 'mayring_agent' && $this->useOllamaForWorkers()) {
    return $this->callMayringViaPi($systemPrompt, $messages, $configKey, $workspace);
}
```

**Aktivierung via `.env`:**
```
CLAUDE_USE_OLLAMA_WORKERS=true
```

Wenn aktiv: RAG-Vorsuche (5 Chunks) via `MayringMcpClient::searchDocuments()` → vollständiger Kontext wird an Pi-Server (`http://host.docker.internal:8091/pi-task`) übergeben → **Keine Anthropic-API-Kosten**.

---

### Weg 3 — Redis-Caching im MayringMcpClient

**Fix (in `app/Services/MayringMcpClient.php`):**

| Methode | Cache-TTL | Cache-Key |
|---------|-----------|-----------|
| `searchDocuments()` | 30 min (konfigurierbar via `MAYRING_MCP_CACHE_TTL`) | `mayring_search:{md5(query)}:{md5(categories)}:{top_k}` |
| `ingestAndCategorize()` | 60 min (konfigurierbar via `MAYRING_MCP_INGEST_CACHE_TTL`) | `mayring_ingest:{md5(content)}:{source_id}` |

Gleiche Query im Tool-Use-Loop kostet nur beim ersten Call — alle Wiederholungen kommen aus Redis.

---

## Production Deployment Plan

### Architektur auf dem Production-Server

```
Internet
    │
    ├── app.linn.games (Laravel, Port 443)
    │       │
    │       ├── → MayringCoder MCP (intern, Port 8090)
    │       └── → Pi Agent Server (intern, Port 8091)
    │
    └── [KEIN externer Ollama-Port]
            │
            └── Ollama (intern only, Port 11434)
                    └── Modell: qwen3.5:2b
```

### Neue Datei: `docker/docker-compose.production.yml`

Zu erstellen mit folgenden Services:

**1. `ollama` — intern only (kein externer Port)**
```yaml
ollama:
  image: ollama/ollama:latest
  # KEIN ports: mapping — intern only
  expose:
    - "11434"
  volumes:
    - ollama_models:/root/.ollama
  restart: unless-stopped
  healthcheck:
    test: ["CMD", "ollama", "list"]
    interval: 10s
    timeout: 15s
    retries: 10
```

**2. `model-pull` — zieht `qwen3.5:2b` beim ersten Start**
```yaml
model-pull:
  image: ollama/ollama:latest
  depends_on:
    ollama:
      condition: service_healthy
  entrypoint: /bin/sh
  command: -c "ollama pull ${OLLAMA_MODEL:-qwen3.5:2b}"
  volumes:
    - ollama_models:/root/.ollama
  restart: "no"
  env_file: .env.production
```

**3. `mcp-server` — MayringCoder MCP auf Port 8090**
```yaml
mcp-server:
  build: ..
  depends_on:
    ollama:
      condition: service_healthy
  ports:
    - "127.0.0.1:8090:8090"   # nur localhost!
  volumes:
    - ./cache:/app/cache
  env_file: .env.production
  environment:
    OLLAMA_URL: http://ollama:11434
    MCP_TRANSPORT: http
    MCP_HTTP_HOST: "0.0.0.0"
    MCP_HTTP_PORT: "8090"
  restart: unless-stopped
```

**4. `pi-server` — Pi Agent auf Port 8091**
```yaml
pi-server:
  build: ..
  depends_on:
    ollama:
      condition: service_healthy
  ports:
    - "127.0.0.1:8091:8091"   # nur localhost!
  env_file: .env.production
  environment:
    OLLAMA_URL: http://ollama:11434
    OLLAMA_MODEL: ${OLLAMA_MODEL:-qwen3.5:2b}
  command: python pi_server.py --host 0.0.0.0 --port 8091
  restart: unless-stopped
```

### Neue Datei: `.env.production`

```dotenv
# MayringCoder Production — Server app.linn.games
# Dieses File NICHT ins Git committen!

# Modell — Qwen3.5 Instruct (allgemein, tool-use fähig, kein Code-Bias)
OLLAMA_MODEL=qwen3.5:2b

# Auth-Token (Laravel: services.mayring_mcp.auth_token)
MCP_AUTH_TOKEN=<secret>

# Ollama intern
OLLAMA_URL=http://ollama:11434

# MCP HTTP
MCP_HTTP_PORT=8090
MCP_HTTP_HOST=0.0.0.0

# Pi Agent
PI_AGENT_PORT=8091
```

### Deploy-Kommandos auf dem Server

```bash
# Aus dem MayringCoder-Verzeichnis:

# Erster Start (zieht qwen3.5:2b, dauert ~3 min bei 1.5GB)
docker compose -f docker/docker-compose.production.yml --env-file .env.production up -d

# Logs prüfen
docker compose -f docker/docker-compose.production.yml logs -f model-pull
docker compose -f docker/docker-compose.production.yml logs -f mcp-server

# Nach erfolgreichem Pull:
docker compose -f docker/docker-compose.production.yml logs mcp-server | tail -20
# → "Application startup complete" auf Port 8090

# Laravel .env prüfen (app.linn.games):
# MAYRING_MCP_ENDPOINT=http://localhost:8090
# MAYRING_MCP_AUTH_TOKEN=<secret>
# PI_AGENT_URL=http://localhost:8091
```

---

## Laravel-seitige Konfiguration (app.linn.games)

In `config/services.php` bzw. `.env` der Laravel-App:

```dotenv
# MayringCoder MCP
MAYRING_MCP_ENDPOINT=http://localhost:8090
MAYRING_MCP_AUTH_TOKEN=<gleicher secret wie oben>
MAYRING_MCP_TIMEOUT=60
MAYRING_MCP_CACHE_TTL=1800
MAYRING_MCP_INGEST_CACHE_TTL=3600

# Pi Agent (Ollama-Routing)
PI_AGENT_URL=http://localhost:8091
CLAUDE_USE_OLLAMA_WORKERS=true   # Mayring-Agent läuft lokal, keine Anthropic-Kosten
```

---

## Offene Tasks

- [ ] `docker/docker-compose.production.yml` erstellen (s. o.)
- [ ] `.env.production` erstellen (nicht committen → `.gitignore` prüfen)
- [ ] `docker/docker-compose.production.yml` testen: `docker compose ... up -d` auf dem Server
- [ ] Laravel `.env` auf dem Server aktualisieren (MAYRING_MCP_ENDPOINT, PI_AGENT_URL)
- [ ] Smoke-Test: ein Mayring-Agent-Lauf via UI → Logs prüfen ob Pi-Routing greift (`CLAUDE_USE_OLLAMA_WORKERS=true`)

---

## Daten-Strategie (langfristig)

Production-Runs mit `qwen3.5:2b` erzeugen qualitative Kategorisierungen. Diese können als Fine-Tuning-Dataset genutzt werden:

1. `ingestAndCategorize()`-Ergebnisse in `cache/` speichern
2. Periodischer Export als JSONL (Input: Forschungstext, Output: Mayring-Kategorien)
3. Unsloth Fine-Tuning (`unsloth_compiled_cache/` bereits vorhanden) → `mayring-research:2b`
4. Dann Production auf Custom-Modell umstellen

Bis dahin: `qwen3.5:2b` Instruct ist ausreichend.

# API-Dokumentation — app.linn.games

> Alle Endpunkte, Authentifizierung und Rate-Limits für externe Systeme (Langdock, MCP-Server).

---

## 🔐 Authentifizierung

Alle MCP und Embedding-Endpunkte nutzen eine einheitliche Token-basierte Authentifizierung. Der Token ist in der Umgebungsvariable `MCP_AUTH_TOKEN` konfiguriert.

**Akzeptierte Authentifizierungs-Methoden (in dieser Reihenfolge):**

1. **Bearer Token** (HTTP-Header):
   ```bash
   curl -H "Authorization: Bearer <MCP_AUTH_TOKEN>" https://app.linn.games/mcp/sse
   ```

2. **API-Key** (HTTP-Header):
   ```bash
   curl -H "X-API-Key: <MCP_AUTH_TOKEN>" https://app.linn.games/mcp/sse
   ```

3. **Query-Parameter** (Fallback für problematische Proxies):
   ```bash
   curl "https://app.linn.games/mcp/sse?token=<MCP_AUTH_TOKEN>"
   ```

**Hinweis:** Der Synology Reverse Proxy filtert möglicherweise den `Authorization`-Header. In diesem Fall werden Methoden 2 oder 3 verwendet.

---

## 📡 PostgreSQL MCP Endpoint

**Base URL:** `https://app.linn.games/mcp/`

Dieser Endpunkt stellt eine Model Context Protocol (MCP) Schnittstelle für die PostgreSQL-Datenbank bereit. Langdock nutzt ihn, um Datenbankabfragen auszuführen und Änderungen zu speichern.

### SSE (Server-Sent Events) — Initialisierung

**Methode:** `GET`  
**Route:** `/mcp/sse`  
**Auth:** Bearer Token / X-API-Key / Query-Parameter  
**Rate-Limit:** 10 req/s pro IP, Burst bis 20  
**Timeout:** 86400s (24h für SSE-Verbindung)

Initialisiert eine SSE-Verbindung für MCP-Kommunikation. Langdock nutzt diese Route, um:
- Eine Authentifizierung zu erhalten
- Eine `session_id` zu bekommen (ungeratbar, sichert alle folgenden Requests)
- Das MCP-Protokoll zu initialisieren

**Beispiel:**
```bash
curl -H "X-API-Key: $MCP_AUTH_TOKEN" \
  https://app.linn.games/mcp/sse

# Erwartet: SSE-Stream mit initialisierter session_id
# z.B.: data: {"type": "session", "session_id": "abc123..."}
```

### Messages — MCP-Anfragen

**Methode:** `POST`  
**Route:** `/messages/`  
**Auth:** Request-Header `session_id` (aus SSE-Handshake) — **kein Token nötig**  
**Rate-Limit:** 10 req/s, Burst bis 200  
**Timeout:** 86400s

Sendet eine MCP-Nachricht (z.B. eine Datenbankabfrage) an den Server.

**Payload Example:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "query",
    "arguments": {
      "sql": "SELECT * FROM projekte WHERE id = $1",
      "params": ["project-uuid"]
    }
  }
}
```

**Beispiel:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: <session_id>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query","arguments":{"sql":"SELECT * FROM projekte LIMIT 1"}}}' \
  https://app.linn.games/messages/
```

---

## 📄 Paper Search MCP Endpoint

**Base URL:** `https://app.linn.games/paper-mcp/`

Ermöglicht Langdock, wissenschaftliche Paper auf verschiedenen Quellen zu suchen (Google Scholar, IEEE, DOAJ, ACM).

### SSE — Initialisierung

**Methode:** `GET`  
**Route:** `/paper-mcp/sse`  
**Auth:** Bearer Token / X-API-Key / Query-Parameter  
**Rate-Limit:** 10 req/s, Burst bis 20

Initialisiert die Paper-Search MCP-Verbindung (analog zu PostgreSQL MCP).

**Beispiel:**
```bash
curl -H "X-API-Key: $MCP_AUTH_TOKEN" \
  https://app.linn.games/paper-mcp/sse
```

### Messages — Paper-Suchanfragen

**Methode:** `POST`  
**Route:** `/paper-messages/`  
**Auth:** Session-ID (aus SSE-Handshake)  
**Rate-Limit:** 10 req/s, Burst bis 200

Sendet eine Paper-Suchanfrage.

**Payload Example:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "search_papers",
    "arguments": {
      "query": "machine learning systematic review",
      "source": "google_scholar"
    }
  }
}
```

---

## 🧠 Ollama Embedding Endpoint

**Base URL:** `https://app.linn.games/ollama/`

Proxyt Embedding-Anfragen zu den Ollama-Modellen (z.B. `nomic-embed-text`) auf `https://three.linn.games`.

### Health Check

**Methode:** `GET`  
**Route:** `/ollama/v1/tags`  
**Auth:** Bearer Token / X-API-Key / Query-Parameter  
**Rate-Limit:** 10 req/s, Burst bis 20

Prüft verfügbare Embedding-Modelle.

**Beispiel:**
```bash
curl -H "X-API-Key: $MCP_AUTH_TOKEN" \
  https://app.linn.games/ollama/v1/tags

# Erwartet JSON mit verfügbaren Modellen:
# {"models": [{"name": "nomic-embed-text:latest", ...}]}
```

### Embeddings generieren

**Methode:** `POST`  
**Route:** `/ollama/api/embeddings`  
**Auth:** Bearer Token / X-API-Key / Query-Parameter  
**Rate-Limit:** 10 req/s, Burst bis 20  
**Content-Type:** `application/json`

Generiert Vektor-Embeddings für einen Text.

**Payload:**
```json
{
  "model": "nomic-embed-text",
  "input": "Dies ist ein Text, der eingebettet werden soll",
  "stream": false
}
```

**Beispiel:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $MCP_AUTH_TOKEN" \
  -d '{
    "model": "nomic-embed-text",
    "input": "systematic review methodology",
    "stream": false
  }' \
  https://app.linn.games/ollama/api/embeddings

# Erwartet:
# {"embedding": [0.123, -0.456, ...], "embedding_type": "float"}
```

---

## 🔗 Langdock Webhook Endpoint

**Methode:** `POST`  
**Route:** `/api/webhooks/langdock`  
**Auth:** HMAC-Signatur im Header `X-Langdock-Signature`  
**Rate-Limit:** Kein spezielles Limit (nicht MCP)

Langdock sendet Webhook-Payloads an diese Route, um:
- Phase-Ergebnisse zu speichern
- Chat-Nachrichten zu verarbeiten

**HMAC-Signature:**
```
X-Langdock-Signature: sha256=<HMAC(request_body, shared_secret)>
```

**Headers:**
```
X-Langdock-Signature: sha256=...
Content-Type: application/json
```

**Payload Example (nicht normalisiert — Langdock kennt die exakte Struktur):**
```json
{
  "event": "phase_completed",
  "project_id": "uuid",
  "phase": 1,
  "status": "abgeschlossen",
  "data": {...}
}
```

oder für Chat:
```json
{
  "event": "chat_response",
  "workspace_id": "uuid",
  "message": "KI-Antwort",
  "tokens_used": 250
}
```

**Beispiel:**
```bash
# Berechne HMAC
BODY='{"event":"phase_completed","project_id":"...","phase":1}'
SECRET="your-secret"
SIGNATURE=$(echo -n "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | cut -d' ' -f2)

curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-Langdock-Signature: sha256=$SIGNATURE" \
  -d "$BODY" \
  https://app.linn.games/api/webhooks/langdock
```

**Status-Codes:**
- `200 OK` — Webhook erfolgreich verarbeitet
- `403 Forbidden` — Ungültige oder fehlende HMAC-Signatur
- `422 Unprocessable Entity` — Payload-Validierung fehlgeschlagen

---

## 🎯 Sanctum User Endpoint

**Methode:** `GET`  
**Route:** `/api/user`  
**Auth:** Sanctum Bearer Token (Session/Cookie)  
**Rate-Limit:** Kein spezielles Limit

Diese Route ist öffentlich dokumentiert, wird aber normalerweise von der Frontend-App genutzt, nicht von Langdock.

**Beispiel:**
```bash
curl -H "Authorization: Bearer <sanctum_token>" \
  https://app.linn.games/api/user

# Erwartet:
# {"id": 1, "name": "User Name", "email": "user@example.com", ...}
```

---

## 📊 Rate Limiting

Alle MCP-Endpunkte nutzen die Nginx `limit_req_zone`:

| Endpunkt | Limit | Burst | Ergebnis bei Überschreitung |
|----------|-------|-------|---------------------------|
| `/mcp/`, `/paper-mcp/`, `/ollama/` | 10 req/s | 20 | HTTP 429 Too Many Requests |
| `/messages/`, `/paper-messages/` | 10 req/s | 200 | HTTP 429 |

**Beispiel Fehler:**
```
HTTP/1.1 429 Too Many Requests
Retry-After: 1
```

---

## ✅ Health Checks

**PostgreSQL MCP:**
```bash
curl -H "X-API-Key: $MCP_AUTH_TOKEN" https://app.linn.games/mcp/sse
# ✅ 200 OK mit SSE-Stream
```

**Paper-Search MCP:**
```bash
curl -H "X-API-Key: $MCP_AUTH_TOKEN" https://app.linn.games/paper-mcp/sse
# ✅ 200 OK mit SSE-Stream
```

**Ollama Embeddings:**
```bash
curl -H "X-API-Key: $MCP_AUTH_TOKEN" https://app.linn.games/ollama/v1/tags
# ✅ 200 OK mit verfügbaren Modellen
```

**Langdock Webhook (Test):**
```bash
curl -X POST -H "X-Langdock-Signature: sha256=test" \
  https://app.linn.games/api/webhooks/langdock
# ⚠️ 403 Forbidden (HMAC ungültig) — aber Endpoint erreichbar
```

---

## 🐛 Fehlerbehandlung

Alle Endpunkte folgen JSON Error-Format:

```json
{
  "error": "Kurzbeschreibung",
  "message": "Ausführliche Fehlermeldung (optional)"
}
```

**Häufige Fehlerszenarien:**

| Szenario | HTTP | Response |
|----------|------|----------|
| Authentifizierung fehlt/ungültig | 401 | `{"error": "Unauthorized"}` |
| Rate-Limit überschritten | 429 | `{"error": "Too Many Requests"}` |
| Webhook-Signatur ungültig | 403 | `{"error": "Forbidden"}` |
| Datenbankfehler (MCP) | 500 | `{"error": "Internal Server Error", "message": "..."}` |

---

## 📚 Referenzen

- **MCP Spezifikation:** https://modelcontextprotocol.io
- **Ollama Dokumentation:** https://ollama.ai
- **Nginx Rate Limiting:** https://nginx.org/en/docs/http/ngx_http_limit_req_module.html
- **Laravel Sanctum:** https://laravel.com/docs/sanctum

---

## 🔄 Changelog

| Datum | Version | Änderung |
|-------|---------|----------|
| 2026-04-03 | 1.0 | Initial — MCP, Paper-Search, Ollama, Webhook dokumentiert |

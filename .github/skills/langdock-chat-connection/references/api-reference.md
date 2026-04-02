# API-Referenz — Langdock Chat-Connection

## Option A — Agents Completions API

### Endpoint

```
POST https://app.langdock.com/api/v1/agents/{agent_id}/completions
```

### Headers

| Header | Wert | Pflicht |
|--------|------|---------|
| `Authorization` | `Bearer {LANGDOCK_API_KEY}` | Ja |
| `Content-Type` | `application/json` | Ja |

### Request Body

```json
{
  "messages": [
    { "role": "user", "content": "Meine Frage" }
  ],
  "stream": false
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `messages` | Array | UIMessage-Format (Vercel AI SDK kompatibel) |
| `messages[].role` | String | `"user"` oder `"assistant"` |
| `messages[].content` | String | Nachrichtentext |
| `stream` | Boolean | `true` für SSE-Streaming, `false` für JSON-Antwort |

### Response (synchron, stream: false)

```json
{
  "role": "assistant",
  "content": "Die Antwort des Agenten..."
}
```

### Response (Streaming, stream: true)

SSE-Events im Vercel AI SDK Streaming-Format:
```
data: {"role":"assistant","content":"Die "}
data: {"role":"assistant","content":"Antwort "}
data: {"role":"assistant","content":"des Agenten..."}
data: [DONE]
```

### Fehler

| Status | Bedeutung |
|--------|-----------|
| 401 | API-Key ungültig oder Agent nicht mit Key geteilt |
| 403 | Agent nicht freigegeben für diesen API-Key |
| 404 | Agent-ID nicht gefunden |
| 429 | Rate Limit überschritten |

---

## Option B — Workflow Webhook

### Ausgehend: App → Langdock Webhook

#### Endpoint

```
POST https://app.langdock.com/api/hooks/workflows/{workflow_id}
```

Optional mit Secret:
```
POST https://app.langdock.com/api/hooks/workflows/{workflow_id}?secret={SECRET}
```

#### Headers

| Header | Wert | Pflicht |
|--------|------|---------|
| `Authorization` | `Bearer {LANGDOCK_API_KEY}` | Ja |
| `Content-Type` | `application/json` | Ja |

#### Request Body

```json
{
  "prompt": "Die Nachricht des Users"
}
```

#### Response

```
HTTP/1.1 202 Accepted

{
  "message": "Webhook processing started",
  "_metadata": {
    "executionId": "a08a8c45-d00f-4465-8c06-58c7b39ce800",
    "triggeredAt": "2026-04-02T13:52:57.224Z"
  }
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `message` | String | Immer `"Webhook processing started"` |
| `_metadata.executionId` | String (UUID) | Eindeutige Ausführungs-ID |
| `_metadata.triggeredAt` | String (ISO 8601) | Zeitstempel des Triggers |

**Wichtig:** Die Antwort enthält KEINEN Output. Der Output kommt asynchron via Callback.

---

### Eingehend: Langdock → App Callback

#### Endpoint (app.linn.games)

```
POST {APP_URL}/api/webhooks/langdock-chat-callback
```

Route definiert in `routes/api.php`, geschützt durch `VerifyLangdockSignature`.

#### Headers (von Langdock gesendet)

| Header | Wert | Beschreibung |
|--------|------|--------------|
| `X-Langdock-Signature` | HMAC-SHA256 Hex-String | Signatur über `timestamp.body` |
| `X-Langdock-Timestamp` | Unix-Timestamp (String) | Zeitpunkt der Signierung |
| `Content-Type` | `application/json` | |

#### Payload (von Langdock gesendet)

```json
{
  "execution_id": "a08a8c45-d00f-4465-8c06-58c7b39ce800",
  "output": "Hallo! 👋 Schön, dass du dich meldest! Wie kann ich dir heute helfen?",
  "success": true
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `execution_id` | String | Muss mit `_metadata.executionId` vom Trigger übereinstimmen |
| `output` | String \| null | Antworttext des Agenten |
| `result` | String \| null | Alternativer Feldname (Fallback) |
| `success` | Boolean \| null | Ob die Verarbeitung erfolgreich war |

#### Response (von App zurück an Langdock)

Erfolg:
```json
{ "status": "ok" }
```

Kein pending Message gefunden:
```json
HTTP 404
{ "error": "No pending message found" }
```

---

## Signatur-Verifizierung

### Algorithmus

```
expected = HMAC-SHA256(
    key  = LANGDOCK_WEBHOOK_SECRET,
    data = "{X-Langdock-Timestamp}.{raw_body}"
)

valid = hash_equals(expected, X-Langdock-Signature)
        AND abs(time() - X-Langdock-Timestamp) <= 300
        AND signature NOT IN redis_nonce_cache
```

### PHP-Implementierung (Referenz)

```php
$expectedSignature = hash_hmac(
    'sha256',
    $timestamp . '.' . $request->getContent(),
    config('services.langdock.secret')
);

if (! hash_equals($expectedSignature, $signature)) {
    abort(403, 'Invalid signature.');
}
```

---

## ENV-Variablen (Übersicht)

| Variable | Verwendung | Beispiel |
|----------|-----------|---------|
| `LANGDOCK_API_KEY` | Bearer-Token für ausgehende Requests | `sk-gPaGD22F...` |
| `LANGDOCK_WEBHOOK_SECRET` | HMAC-Secret für eingehende Callbacks | `3e2138e7944a...` |
| `LANGDOCK_WEBHOOK_URL` | Workflow-Webhook-URL (optional, wenn in Webhook-Model) | `https://app.langdock.com/api/hooks/workflows/...` |

Config-Mapping in `config/services.php`:
```php
'langdock' => [
    'api_key'     => env('LANGDOCK_API_KEY'),
    'webhook_url' => env('LANGDOCK_WEBHOOK_URL'),
    'secret'      => env('LANGDOCK_WEBHOOK_SECRET'),
],
```

---

## Langdock Workflow-Konfiguration (UI)

### Webhook-Trigger Node
- Body-Eingabe: `{ "prompt": "..." }` (kommt von der App)
- Optional: Secret aktivieren (wird als `?secret=...` an URL angehängt)

### Chat Answer Agent Node
- Eingabe: `Chat` (aus Webhook-Body → `prompt`)
- Ausgabe: Strukturiert (`output`, `success`)

### Respond to Webhook Node
- Eingabe: Ausgabe des Chat-Agent-Nodes
- **Kritisch:** Der Body-Template muss valides JSON produzieren
- Häufiger Fehler: `Unexpected token '{'` → Template-Syntax in Langdock prüfen
- Die Ausgabe wird an die Callback-URL des Webhook-Triggers zurückgesendet

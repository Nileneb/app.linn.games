# Architektur — Langdock Chat-Connection

## Datenfluss (Option B — Workflow Webhook, async)

```
┌──────────────────────────────────────────────────────────────────────┐
│  Browser (Livewire)                                                   │
│                                                                       │
│  User tippt Nachricht                                                │
│       │                                                              │
│       ▼                                                              │
│  big-research-chat.blade.php                                         │
│  sendMessage()                                                        │
│       │                                                              │
│       ├─ 1. ChatMessage (role=user, content=text) speichern          │
│       │                                                              │
│       ├─ 2. HTTP POST → Langdock Webhook-URL                        │
│       │      Headers: Authorization: Bearer {API_KEY}                │
│       │      Body: { "prompt": "..." }                               │
│       │                                                              │
│       ├─ 3. Response: HTTP 202 { "_metadata": { "executionId": "X" }}│
│       │                                                              │
│       ├─ 4. ChatMessage (role=assistant, content=null,               │
│       │      langdock_execution_id="X") speichern                    │
│       │                                                              │
│       └─ 5. $loading = true                                          │
│                                                                       │
│  wire:poll.3s="pollForResponse"                                       │
│       │                                                              │
│       └─ Prüft: existiert pending Message (content=null)?            │
│           ├─ Ja → weiter polling                                     │
│           └─ Nein → $loading = false, chat-updated dispatch          │
└──────────────────────────────────────────────────────────────────────┘

                         ║ (async, 2-30s)
                         ▼

┌──────────────────────────────────────────────────────────────────────┐
│  Langdock Cloud                                                       │
│                                                                       │
│  Webhook-Trigger empfängt Request                                    │
│       │                                                              │
│       ▼                                                              │
│  Chat Answer Agent (oder custom Workflow-Logik)                      │
│       │                                                              │
│       ▼                                                              │
│  "Respond to Webhook"-Node                                           │
│       │                                                              │
│       └─ POST → {APP_URL}/api/webhooks/langdock-chat-callback        │
│              Headers: X-Langdock-Signature, X-Langdock-Timestamp     │
│              Body: { "execution_id": "X", "output": "...",           │
│                      "success": true }                               │
└──────────────────────────────────────────────────────────────────────┘

                         ║
                         ▼

┌──────────────────────────────────────────────────────────────────────┐
│  Laravel Backend                                                      │
│                                                                       │
│  POST /api/webhooks/langdock-chat-callback                           │
│       │                                                              │
│       ├─ VerifyLangdockSignature Middleware                          │
│       │      HMAC-SHA256 + Timestamp ±5min + Nonce-Replay            │
│       │                                                              │
│       ├─ LangdockChatCallbackController::handle()                    │
│       │      ChatMessage WHERE langdock_execution_id = X             │
│       │      AND role = assistant AND content IS NULL                 │
│       │                                                              │
│       └─ UPDATE content = output                                     │
│           → Nächster Poll holt aktualisierte Nachricht              │
└──────────────────────────────────────────────────────────────────────┘
```

## Datenbankschema (chat_messages)

```sql
CREATE TABLE chat_messages (
    id              UUID PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    webhook_id      UUID,
    role            VARCHAR(20) NOT NULL,          -- 'user' | 'assistant'
    langdock_execution_id VARCHAR(100),            -- NULL für User-Messages
    content         TEXT,                           -- NULL = pending (async)
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP
);

CREATE INDEX idx_chat_messages_execution_id ON chat_messages (langdock_execution_id);
```

## Sequenzdiagramm

```
User          Livewire           Laravel            Langdock
 │               │                  │                   │
 │──Nachricht──▶│                  │                   │
 │               │──save user msg─▶│                   │
 │               │──HTTP POST─────────────────────────▶│
 │               │◀─HTTP 202 + executionId────────────│
 │               │──save pending──▶│                   │
 │               │                  │                   │
 │               │◀─poll (3s)──────│                   │
 │               │  (still pending) │                   │
 │               │                  │                   │
 │               │                  │◀──callback POST──│
 │               │                  │──verify HMAC     │
 │               │                  │──update content   │
 │               │                  │                   │
 │               │◀─poll (3s)──────│                   │
 │               │  (content found) │                   │
 │◀─Antwort─────│                  │                   │
```

## Datenfluss (Option A — Agents API, synchron)

```
User          Livewire           Laravel                     Langdock
 │               │                  │                            │
 │──Nachricht──▶│                  │                            │
 │               │──save user msg─▶│                            │
 │               │──HTTP POST───────────────────────────────────▶│
 │               │                  │  (Agent verarbeitet)       │
 │               │◀─HTTP 200 + content──────────────────────────│
 │               │──save assistant─▶│                            │
 │◀─Antwort─────│                  │                            │
```

**Vorteile Option A:**
- Kein Callback-Endpoint nötig
- Kein Polling nötig
- Streaming möglich (SSE)
- Multi-Turn-Kontext automatisch

**Nachteile Option A:**
- Blockiert HTTP-Verbindung während Agent denkt
- Timeout-Risiko bei langer Verarbeitung

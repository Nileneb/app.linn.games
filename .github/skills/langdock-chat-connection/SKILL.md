---
name: langdock-chat-connection
description: "Langdock Chat-Integration für app.linn.games. Use when: connecting chat UI to Langdock Agent, debugging Langdock API responses, setting up Agent API keys. Uses Agents Completions API (synchron)."
argument-hint: "Describe the Langdock integration task (e.g., 'neuen Chat-Endpoint anlegen', 'Agent API debuggen')"
---

# Langdock Chat-Connection — app.linn.games

Dieses Skill dokumentiert die Anbindung des Chat-Fensters an Langdock via Agents Completions API im Kontext von app.linn.games.

## Integrationsmethode: Agents Completions API (synchron)

Siehe [Architektur-Referenz](./references/architecture.md) für Details zum Datenfluss.
Siehe [API-Referenz](./references/api-reference.md) für Endpoints, Payloads und Fehler-Codes.

---

## Option A — Agents Completions API (empfohlen für echte Chat-UX)

### Setup-Schritte

1. **API-Schlüssel erstellen** (Workspace-Admin)
   - Langdock Workspace-Einstellungen → Produkt API
   - API-Schlüssel erstellen, Berechtigungen mind. "Agent API"
   - Key sicher ablegen (nicht erneut einsehbar nach Erstellung)

2. **Agent für den API-Schlüssel freigeben** (Admin)
   - In Agents den gewünschten Agenten öffnen (muss gespeichert sein)
   - Teilen → den API-Schlüssel hinzufügen (Agent mit Key „teilen")

3. **Agent-ID holen**
   - Aus der URL im Agent-Editor: `https://app.langdock.com/agent/<AGENT_ID>`

4. **Serverseitig aufrufen** (PFLICHT — Browser-Requests werden blockiert)
   - UI → dein Backend (`/api/chat`) → Langdock Agents API → Backend streamt/returned → UI

### Agents Completions Endpoint

```
POST https://app.langdock.com/api/v1/agents/{agent_id}/completions
Authorization: Bearer {LANGDOCK_API_KEY}
Content-Type: application/json

{
  "messages": [
    { "role": "user", "content": "Meine Frage" }
  ],
  "stream": true
}
```

- **Streaming**: `stream: true` → Response im Vercel AI SDK Streaming-Format (SSE)
- **Synchron**: `stream: false` → komplette Antwort in einem JSON-Objekt
- **Vercel AI SDK kompatibel** (UIMessage-Format) → `useChat()` Hook funktioniert direkt

### Laravel-Implementierung (Option A — aktiv)

Die gesamte Logik befindet sich in der Volt-Komponente `resources/views/livewire/chat/big-research-chat.blade.php`:

```php
// sendMessage() — Synchroner Agent-Call mit Multi-Turn-Kontext
$agentId = config('services.langdock.agent_id');

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . config('services.langdock.api_key'),
    'Content-Type'  => 'application/json',
])->timeout(120)->post(
    "https://app.langdock.com/api/v1/agents/{$agentId}/completions",
    [
        'messages' => $this->buildMessages($userMessage), // letzte 20 Nachrichten + aktuelle
        'stream'   => false,
    ]
);

// buildMessages() baut den Kontext aus den letzten 20 ChatMessages auf
// Response-Content wird direkt als assistant-ChatMessage gespeichert
```

**Beteiligte Dateien:**

| Datei | Rolle |
|-------|-------|
| `resources/views/livewire/chat/big-research-chat.blade.php` | Volt-Komponente: sendMessage(), buildMessages(), UI mit wire:loading |
| `app/Models/ChatMessage.php` | Model für Chat-Nachrichten |
| `app/Services/LangdockAgentService.php` | Zentrale Langdock Agents Completions API |
| `config/services.php` | `langdock.api_key`, `langdock.agent_id` + 4 weitere Agent-IDs |

**Kritische ENV-Variablen:**

```env
LANGDOCK_API_KEY=sk-...              # API-Key für Bearer-Auth
LANGDOCK_AGENT_ID=...                # Dashboard-Chat Agent-ID
SCOPING_MAPPING_AGENT=...           # P1–P3 Agent
SEARCH_AGENT=...                     # P4 Agent
REVIEW_AGENT=...                     # P5–P8 Agent
RESEARCH_RETRIEVAL_AGENT=...         # Paper-Download Agent
```

---

## Sicherheit

- **Bearer-Token**: `Authorization: Bearer {LANGDOCK_API_KEY}` bei jedem Request
- **Immer serverseitig**: Nie direkt aus dem Browser (Key-Leak-Gefahr)
- **MCP-Token**: Eingehende Agent-Datenbankzugriffe via `VerifyMcpToken`-Middleware

---

## Debugging

### Chat zeigt keine Antwort

1. Prüfe ob `LANGDOCK_AGENT_ID` in `.env` gesetzt ist
2. Prüfe ob `LANGDOCK_API_KEY` gültig ist
3. Prüfe ob der Agent in Langdock für den API-Key freigegeben ist
4. Prüfe Laravel-Logs: `storage/logs/laravel.log`
5. Prüfe Timeout (120s) — bei langen Agent-Antworten ggf. erhöhen

### Fehlermeldung "Fehler bei der Verarbeitung"

- Langdock API hat HTTP 4xx/5xx zurückgegeben
- Prüfe Status-Code und Body in Laravel-Logs

### Fehlermeldung "Verbindung fehlgeschlagen"

- Netzwerkproblem oder Langdock nicht erreichbar
- Docker-Container prüfen: `docker compose exec php-fpm curl -I https://app.langdock.com`
5. Prüfe Laravel-Logs: `storage/logs/laravel.log`

### "Denkt nach ..." bleibt hängen

- Pending Message ohne Callback → Langdock Workflow-Ausführung prüfen
- Polling läuft nur bei `$loading=true` → Seite neu laden
- Chat-Verlauf löschen und erneut versuchen

### Fehlermeldung `{"message":"Webhook processing started"}`

- Das ist die **korrekte** 202-Antwort von Langdock (async)
- Wenn dies als Chat-Nachricht erscheint: alter synchroner Code aktiv → Migration + Container-Rebuild

### HMAC-Signatur schlägt fehl

- Secret in `.env` stimmt nicht mit Langdock-Konfiguration überein
- Timestamp zu alt (>5 Min): Serverzeit prüfen (`date` in Container)
- Replay-Schutz: gleicher Request zweimal gesendet

---

## Migration von Option B → Option A (abgeschlossen)

Die Migration wurde durchgeführt. Folgende Schritte wurden umgesetzt:

1. ~~Neuen Controller erstellen~~ → Logik direkt in Volt-Komponente
2. ~~Route ändern~~ → Kein separater Controller nötig, Callback-Route bleibt für Workflows
3. ✅ `sendMessage()` in Volt-Komponente umgeschrieben (synchrone Antwort mit Multi-Turn-Kontext)
4. ✅ `pollForResponse()` und `wire:poll.3s` entfernt — `wire:loading` stattdessen
5. ✅ `VerifyLangdockSignature` auf Dashboard-Chat nicht mehr nötig (kein Callback)
6. ✅ Agent-ID als ENV-Variable: `LANGDOCK_AGENT_ID=...` in `.env` und `.env.example`
7. ✅ Tests aktualisiert für synchrones Verhalten

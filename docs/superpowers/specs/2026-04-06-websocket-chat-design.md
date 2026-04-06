# WebSocket Chat-Integration — Design Spec
**Datum:** 2026-04-06
**Stack:** Laravel 12, Livewire/Volt, Docker, Nginx, Redis
**Ansatz:** Laravel Reverb + Echo (Approach A)

---

## Ziel

Ersetze das 3s-Polling (`wire:poll.3s`) durch eine persistente WebSocket-Verbindung.
Kein Cloud-Dienst, kein externer Broadcaster — Reverb läuft als PHP-nativer Docker-Container im bestehenden Stack.

---

## 1. Architektur & Datenfluss

```
User sendet Nachricht
  → Livewire sendMessage()
  → ProcessChatMessageJob dispatched (Redis queue)
  → Job ruft Langdock API auf
  → ChatMessage::saveAssistantReply() speichert Antwort
  → broadcast(new ChatResponseReady(...))
  → Reverb (via Redis publishing) pusht Event über WS
  → Laravel Echo im Browser empfängt '.chat.response'
  → $wire.$refresh() aktualisiert die Nachrichten
  → 90s-Failsafe-Timeout stoppt Spinner falls WS/Queue ausfällt
```

**Infrastruktur-Komponenten:**

| Komponente      | Rolle                            | Adresse              |
|-----------------|----------------------------------|----------------------|
| Laravel Reverb  | WebSocket-Server (PHP-nativ)     | reverb:8080 (intern) |
| Nginx           | WS-Proxy `/app` → Reverb         | Port 6481 extern     |
| Laravel Echo    | JS-Client im Browser             | —                    |
| Redis           | Queue + Broadcasting             | redis:6379           |

---

## 2. Infrastruktur

### docker-compose.yml — neuer `reverb` Service

```yaml
reverb:
  build:
    context: .
    dockerfile: ./docker/common/php-fpm/dockerfile
    target: production
  command: php artisan reverb:start --host=0.0.0.0 --port=8080
  restart: unless-stopped
  depends_on:
    redis:
      condition: service_healthy
  volumes:
    - linn-storage-production:/var/www/storage
  env_file:
    - .env
  networks:
    - linn-network
```

Kein Port-Mapping nach außen — Nginx proxyt intern.

### Nginx default.conf — WS-Proxy-Blöcke

Einfügen VOR `location /`:

```nginx
# WebSocket (Laravel Reverb)
location /app {
    proxy_pass http://reverb:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $real_scheme;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
}

location /apps {
    proxy_pass http://reverb:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

**Synology Reverse Proxy:** `Upgrade` + `Connection`-Header sind bereits konfiguriert — kein manueller Eingriff nötig.

---

## 3. Backend

### .env-Variablen

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=linn-games
REVERB_APP_KEY=linn-games-key
REVERB_APP_SECRET=linn-games-secret-change-me
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend (öffentlich sichtbar)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=app.linn.games
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`REVERB_HOST=reverb` zeigt auf den Docker-Service. Frontend nutzt die öffentliche Domain.

### app/Events/ChatResponseReady.php — neu

- Implementiert `ShouldBroadcast`
- Constructor-Properties (readonly): `workspaceId`, `userId`, `messageId`, `content`
- `broadcastOn()` → `PrivateChannel("chat.{workspaceId}.{userId}")`
- `broadcastAs()` → `'chat.response'`

### routes/channels.php — neu erstellen

Auth-Callback für den private Channel:

```php
Broadcast::channel(
    'chat.{workspaceId}.{userId}',
    fn($user, $workspaceId, $userId) =>
        (int)$user->id === (int)$userId
        && $user->activeWorkspaceId() === $workspaceId
);
```

### ProcessChatMessageJob — Ergänzung

`saveAssistantReply()` gibt `static` zurück — Return-Wert muss gecaptured werden:

```php
$assistantMsg = ChatMessage::saveAssistantReply($this->workspaceId, $this->userId, $artifact['display_content']);
```

Danach:

```php
broadcast(new ChatResponseReady(
    workspaceId: $this->workspaceId,
    userId:      $this->userId,
    messageId:   $assistantMsg->id,
    content:     $artifact['display_content'],
));
```

Kein `->toOthers()` — Queue-Job hat keinen Socket-Kontext, daher würden alle Verbindungen auf dem privaten Channel erreicht werden (korrekt, da Channel ohnehin user-spezifisch ist).

### Broadcasting-Middleware

In `bootstrap/app.php`:

```php
Broadcast::routes(['middleware' => ['web', 'auth']]);
```

---

## 4. Frontend

### npm-Pakete

```
laravel-echo
pusher-js
```

### resources/js/echo.js — neu

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### resources/js/app.js

```js
import './echo';
```

### big-research-chat.blade.php

**Entfernen:**
- `wire:poll.3s="checkForResponse"` vom Loading-div (Direktive, nicht das div selbst)
- `checkForResponse()` Methode
- `$pendingUserMsgId` Property und alle Zuweisungen (`$this->pendingUserMsgId = ...`), auch in `clearHistory()`

**Hinzufügen im `@script`-Block:**

```js
const userId = @js(auth()->id());
const workspaceId = @js(auth()->user()?->activeWorkspaceId());

if (workspaceId && window.Echo) {
    window.Echo.private(`chat.${workspaceId}.${userId}`)
        .listen('.chat.response', (e) => {
            clearTimeout(timeoutHandle);
            $wire.set('loading', false);
            $wire.$refresh();
            setTimeout(scrollToBottom, 100);
        });
}
```

**90s-Failsafe bleibt** — stoppt Spinner falls Queue-Worker down oder WS-Verbindung verloren geht.

---

## 5. Tests

```php
test('job broadcasts ChatResponseReady via websocket', function () {
    Event::fake([ChatResponseReady::class]);
    Http::fake(['*' => Http::response([...])]);

    // ... dispatch job ...

    Event::assertDispatched(ChatResponseReady::class,
        fn($e) => $e->userId === $user->id
            && $e->workspaceId === $workspaceId
    );
});
```

---

## 6. Deployment-Checkliste

| Schritt                         | Datei                        |
|---------------------------------|------------------------------|
| `composer require laravel/reverb` | Terminal                   |
| `php artisan reverb:install`    | Terminal                     |
| BROADCAST_CONNECTION=reverb     | .env                         |
| REVERB_* + VITE_REVERB_*        | .env                         |
| reverb Service                  | docker-compose.yml           |
| /app + /apps WS-Proxy           | docker/common/nginx/default.conf |
| ChatResponseReady Event         | app/Events/                  |
| Channel-Auth                    | routes/channels.php          |
| broadcast() im Job              | ProcessChatMessageJob.php    |
| laravel-echo + pusher-js        | package.json                 |
| echo.js                         | resources/js/                |
| import './echo'                  | resources/js/app.js          |
| wire:poll + checkForResponse entfernen | big-research-chat.blade.php |
| Echo-Listener hinzufügen        | big-research-chat.blade.php  |
| Broadcast::routes()             | bootstrap/app.php            |
| npm run build                   | Terminal                     |
| Tests anpassen                  | tests/                       |

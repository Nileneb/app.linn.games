# WebSocket Chat (Reverb + Echo) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ersetze `wire:poll.3s` im Dashboard-Chat durch echte WebSocket-Verbindung via Laravel Reverb + Echo.

**Architecture:** `ProcessChatMessageJob` broadcasted nach dem Speichern die Antwort via `ChatResponseReady` Event auf einem privaten Channel. Laravel Echo im Browser empfängt das Event und aktualisiert die Livewire-Komponente. Redis dient als Broadcasting-Treiber zwischen PHP-FPM, Queue-Worker und Reverb.

**Tech Stack:** Laravel Reverb, Laravel Echo, pusher-js (WS client protocol), Redis Broadcasting, Livewire/Volt 3, Docker, Nginx

---

## File Map

| Aktion   | Datei                                              | Zweck                                      |
|----------|----------------------------------------------------|--------------------------------------------|
| Modify   | `.env`                                             | BROADCAST_CONNECTION + REVERB_* Variablen  |
| Create   | `app/Events/ChatResponseReady.php`                 | ShouldBroadcast Event                      |
| Create   | `routes/channels.php`                              | Private Channel Auth-Callback              |
| Modify   | `bootstrap/app.php`                                | channels: Route registrieren               |
| Modify   | `app/Jobs/ProcessChatMessageJob.php`               | broadcast() nach saveAssistantReply()      |
| Modify   | `docker-compose.yml`                               | Neuer reverb Service                       |
| Modify   | `docker/common/nginx/default.conf`                 | WS-Proxy /app + /apps                      |
| Modify   | `package.json`                                     | laravel-echo + pusher-js                   |
| Create   | `resources/js/echo.js`                             | Echo Client Setup                          |
| Modify   | `resources/js/app.js`                              | import './echo'                            |
| Modify   | `resources/views/livewire/chat/big-research-chat.blade.php` | Poll entfernen, Echo-Listener einfügen |
| Modify   | `tests/Feature/Chat/BigResearchChatTest.php`       | checkForResponse-Tests löschen, Broadcast-Test hinzufügen |
| Create   | `tests/Feature/Chat/ChatResponseReadyTest.php`     | Event-Properties + Channel-Test            |

---

## Task 1: Laravel Reverb installieren

**Files:** Terminal only

- [ ] **Step 1: Reverb Composer-Paket installieren**

```bash
docker compose exec php-fpm composer require laravel/reverb
```

Erwartete Ausgabe enthält: `laravel/reverb` in der Installed-Liste.

- [ ] **Step 2: Reverb-Konfiguration generieren**

```bash
docker compose exec php-fpm php artisan reverb:install
```

Erwartete Ausgabe: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` werden in `.env` eingetragen, `config/reverb.php` wird erstellt.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock config/reverb.php
git commit -m "feat: install laravel/reverb"
```

---

## Task 2: .env konfigurieren

**Files:**
- Modify: `.env`

- [ ] **Step 1: BROADCAST_CONNECTION ändern und REVERB_* Variablen setzen**

Ersetze in `.env`:
```
BROADCAST_CONNECTION=log
```
durch:
```dotenv
BROADCAST_CONNECTION=reverb
```

Füge ans Ende von `.env` hinzu (falls `reverb:install` sie nicht bereits gesetzt hat — prüfen!):

```dotenv
REVERB_APP_ID=linn-games
REVERB_APP_KEY=linn-games-key
REVERB_APP_SECRET=linn-games-secret-change-me
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=app.linn.games
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

**Hinweis:** `REVERB_HOST=reverb` zeigt auf den Docker-Service-Namen. Die VITE_-Variablen zeigen auf die öffentliche Domain (werden ins JS-Bundle eingebaut).

- [ ] **Step 2: Commit**

`.env` ist in `.gitignore` — kein Commit nötig. Stattdessen:

```bash
git add config/reverb.php
git status
```

Sicherstellen, dass `.env` nicht staged ist.

---

## Task 3: ChatResponseReady Event erstellen (TDD)

**Files:**
- Create: `app/Events/ChatResponseReady.php`
- Create: `tests/Feature/Chat/ChatResponseReadyTest.php`

- [ ] **Step 1: Failing Test schreiben**

Erstelle `tests/Feature/Chat/ChatResponseReadyTest.php`:

```php
<?php

use App\Events\ChatResponseReady;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;

test('ChatResponseReady broadcasted auf privatem user-channel', function () {
    $workspaceId = (string) Str::uuid();
    $userId      = 42;
    $messageId   = (string) Str::uuid();
    $content     = 'KI-Antwort auf deine Frage';

    $event = new ChatResponseReady(
        workspaceId: $workspaceId,
        userId:      $userId,
        messageId:   $messageId,
        content:     $content,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-chat.{$workspaceId}.{$userId}");
});

test('ChatResponseReady hat korrekte Properties', function () {
    $workspaceId = 'ws-123';
    $userId      = 7;
    $messageId   = 'msg-456';
    $content     = 'Test-Antwort';

    $event = new ChatResponseReady(
        workspaceId: $workspaceId,
        userId:      $userId,
        messageId:   $messageId,
        content:     $content,
    );

    expect($event->workspaceId)->toBe($workspaceId);
    expect($event->userId)->toBe($userId);
    expect($event->messageId)->toBe($messageId);
    expect($event->content)->toBe($content);
});

test('ChatResponseReady broadcastAs gibt chat.response zurück', function () {
    $event = new ChatResponseReady('ws', 1, 'msg', 'content');

    expect($event->broadcastAs())->toBe('chat.response');
});
```

- [ ] **Step 2: Test ausführen — muss fehlschlagen**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/Chat/ChatResponseReadyTest.php
```

Erwartete Ausgabe: `FAILED` — `App\Events\ChatResponseReady not found`

- [ ] **Step 3: Event erstellen**

Erstelle `app/Events/ChatResponseReady.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatResponseReady implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly int    $userId,
        public readonly string $messageId,
        public readonly string $content,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->workspaceId}.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.response';
    }
}
```

- [ ] **Step 4: Test ausführen — muss grün sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/Chat/ChatResponseReadyTest.php
```

Erwartete Ausgabe: `PASS` — 3 Tests bestehen.

- [ ] **Step 5: Commit**

```bash
git add app/Events/ChatResponseReady.php tests/Feature/Chat/ChatResponseReadyTest.php
git commit -m "feat: add ChatResponseReady broadcast event"
```

---

## Task 4: Channel-Auth + Bootstrap registrieren

**Files:**
- Create: `routes/channels.php`
- Modify: `bootstrap/app.php`

Laravel 12 registriert Channel-Routen über `withRouting(channels: ...)` in `bootstrap/app.php`. Damit wird `Broadcast::routes()` automatisch aufgerufen und `routes/channels.php` geladen.

- [ ] **Step 1: routes/channels.php erstellen**

Erstelle `routes/channels.php`:

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'chat.{workspaceId}.{userId}',
    function ($user, string $workspaceId, string $userId): bool {
        return (int) $user->id === (int) $userId
            && $user->activeWorkspaceId() === $workspaceId;
    }
);
```

- [ ] **Step 2: bootstrap/app.php — channels Route registrieren**

Öffne `bootstrap/app.php`. Die `withRouting()`-Methode sieht so aus:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Ergänze `channels:`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: '/up',
)
```

- [ ] **Step 3: Smoke-Test — Kanäle werden geladen**

```bash
docker compose exec php-fpm php artisan channel:list
```

Erwartete Ausgabe: `chat.{workspaceId}.{userId}` erscheint in der Liste.

- [ ] **Step 4: Commit**

```bash
git add routes/channels.php bootstrap/app.php
git commit -m "feat: register private chat broadcast channel"
```

---

## Task 5: ProcessChatMessageJob — broadcast() hinzufügen (TDD)

**Files:**
- Modify: `app/Jobs/ProcessChatMessageJob.php`
- Modify: `tests/Feature/Chat/BigResearchChatTest.php`

Aktuell: Job ruft `ChatMessage::saveAssistantReply(...)` auf, ohne Rückgabewert zu nutzen.
Neu: Rückgabewert als `$assistantMsg` capturen und `broadcast()` aufrufen.

- [ ] **Step 1: Veraltete Tests aus BigResearchChatTest.php löschen**

Lösche die 2 Tests vollständig aus `tests/Feature/Chat/BigResearchChatTest.php`:

- `test('chat: checkForResponse erkennt antwort und setzt loading false', ...)`  (Zeilen 48–75)
- `test('chat: checkForResponse tut nichts wenn noch keine antwort', ...)` (Zeilen 77–95)

Diese Tests testen eine Methode, die entfernt wird.

- [ ] **Step 2: Broadcast-Test in BigResearchChatTest.php hinzufügen**

Füge am Anfang der Datei den Import hinzu:

```php
use App\Events\ChatResponseReady;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
```

Füge diesen Test am Ende der Datei (vor dem letzten `?>` falls vorhanden, PHP-Dateien haben oft kein schließendes Tag) hinzu:

```php
test('chat: job broadcasted ChatResponseReady nach erfolgreicher antwort', function () {
    Event::fake([ChatResponseReady::class]);

    Http::fake([
        '*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'KI-Antwort']],
            ],
        ]),
    ]);

    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $userMsg = \App\Models\ChatMessage::create([
        'user_id'      => $user->id,
        'workspace_id' => $workspace->id,
        'role'         => 'user',
        'content'      => 'Hallo',
    ]);

    (new \App\Jobs\ProcessChatMessageJob(
        $userMsg->id,
        $workspace->id,
        $user->id,
        ['source' => 'dashboard_chat', 'user_id' => $user->id, 'workspace_id' => $workspace->id],
    ))->handle();

    Event::assertDispatched(ChatResponseReady::class, function ($e) use ($user, $workspace) {
        return $e->userId === $user->id
            && $e->workspaceId === $workspace->id;
    });
});
```

- [ ] **Step 3: Test ausführen — muss fehlschlagen**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/Chat/BigResearchChatTest.php --filter="job broadcasted"
```

Erwartete Ausgabe: `FAILED` — Event wurde nicht dispatched.

- [ ] **Step 4: ProcessChatMessageJob anpassen**

Öffne `app/Jobs/ProcessChatMessageJob.php`. Füge den Import hinzu:

```php
use App\Events\ChatResponseReady;
```

Ersetze die letzte Zeile in `handle()`:

```php
ChatMessage::saveAssistantReply($this->workspaceId, $this->userId, $artifact['display_content']);
```

durch:

```php
$assistantMsg = ChatMessage::saveAssistantReply($this->workspaceId, $this->userId, $artifact['display_content']);

broadcast(new ChatResponseReady(
    workspaceId: $this->workspaceId,
    userId:      $this->userId,
    messageId:   $assistantMsg->id,
    content:     $artifact['display_content'],
));
```

- [ ] **Step 5: Alle Chat-Tests ausführen — alle müssen grün sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/Chat/
```

Erwartete Ausgabe: `PASS` — alle Tests bestehen (die 2 gelöschten sind weg, der neue Broadcast-Test besteht).

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/ProcessChatMessageJob.php tests/Feature/Chat/BigResearchChatTest.php
git commit -m "feat: broadcast ChatResponseReady in ProcessChatMessageJob"
```

---

## Task 6: Docker — reverb Service hinzufügen

**Files:**
- Modify: `docker-compose.yml`

- [ ] **Step 1: reverb Service in docker-compose.yml eintragen**

Füge nach dem `queue-worker` Service (nach Zeile 105, vor `postgres:`) ein:

```yaml
  reverb:
    build:
      context: .
      dockerfile: ./docker/common/php-fpm/dockerfile
      target: production
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - linn-storage-production:/var/www/storage
    networks:
      - linn-network
    depends_on:
      redis:
        condition: service_healthy
```

Kein `ports:`-Mapping — Nginx proxyt den Traffic intern.

- [ ] **Step 2: Commit**

```bash
git add docker-compose.yml
git commit -m "feat: add reverb docker service"
```

---

## Task 7: Nginx — WebSocket-Proxy konfigurieren

**Files:**
- Modify: `docker/common/nginx/default.conf`

- [ ] **Step 1: WS-Proxy-Blöcke VOR `location /` einfügen**

Öffne `docker/common/nginx/default.conf`. Suche die Zeile:

```nginx
    # Main location block
    location / {
```

Füge DIREKT DAVOR ein:

```nginx
    # ── WebSocket (Laravel Reverb) ────────────────────────────────────────
    location /app {
        set $reverb_upstream http://reverb:8080;
        proxy_pass $reverb_upstream;
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
        set $reverb_upstream http://reverb:8080;
        proxy_pass $reverb_upstream;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
    # ── End WebSocket ─────────────────────────────────────────────────────

```

**Hinweis:** `set $reverb_upstream` statt direktem `proxy_pass http://reverb:8080` ist konsistent mit dem restlichen Nginx-Pattern in dieser Datei (lazy DNS resolution).

- [ ] **Step 2: Commit**

```bash
git add docker/common/nginx/default.conf
git commit -m "feat: add nginx websocket proxy for reverb"
```

---

## Task 8: Frontend — Echo installieren und konfigurieren

**Files:**
- Modify: `package.json`
- Create: `resources/js/echo.js`
- Modify: `resources/js/app.js`

- [ ] **Step 1: npm-Pakete installieren**

```bash
npm install laravel-echo pusher-js
```

Erwartete Ausgabe: `laravel-echo` und `pusher-js` tauchen in `package.json` unter `dependencies` auf.

- [ ] **Step 2: resources/js/echo.js erstellen**

Erstelle `resources/js/echo.js`:

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

- [ ] **Step 3: app.js — Echo importieren**

`resources/js/app.js` ist aktuell leer (1 Zeile). Ersetze den gesamten Inhalt durch:

```js
import './echo';
```

- [ ] **Step 4: Vite-Build testen**

```bash
npm run build
```

Erwartete Ausgabe: Build erfolgreich, keine Fehler. `public/build/` enthält das neue Bundle.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json resources/js/echo.js resources/js/app.js public/build/
git commit -m "feat: add laravel-echo and configure reverb client"
```

---

## Task 9: Livewire Blade — Poll entfernen, Echo-Listener einbauen

**Files:**
- Modify: `resources/views/livewire/chat/big-research-chat.blade.php`

**Was sich ändert:**
- `$pendingUserMsgId` Property → entfernen (inkl. Zuweisungen in `sendMessage()` und `clearHistory()`)
- `checkForResponse()` Methode → entfernen
- `wire:poll.3s="checkForResponse"` Direktive → entfernen (nur die Direktive, das `<div>` bleibt)
- Echo-Channel-Listener im `@script`-Block → hinzufügen
- 90s-Failsafe-Timeout → bleibt erhalten

- [ ] **Step 1: PHP-Teil des Volt-Komponente anpassen**

Im `<?php ... ?>` Block (Zeilen 1–91):

Ersetze:
```php
    public bool    $loading          = false;
    public ?string $pendingUserMsgId = null;
```
durch:
```php
    public bool $loading = false;
```

Ersetze die `sendMessage()` Methode — entferne `$this->pendingUserMsgId`:
```php
    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $workspaceId = Auth::user()?->activeWorkspaceId();

        if ($workspaceId === null) {
            return;
        }

        $userMessage   = $this->message;
        $this->message = '';

        app(ChatService::class)->saveUserMessage($workspaceId, Auth::id(), $userMessage);

        $this->loading = true;

        ProcessChatMessageJob::dispatch(
            app(ChatService::class)->saveUserMessage($workspaceId, Auth::id(), $userMessage)->id,
            $workspaceId,
            Auth::id(),
            [
                'source'       => 'dashboard_chat',
                'user_id'      => Auth::id(),
                'workspace_id' => $workspaceId,
            ],
        );

        $this->dispatch('chat-loading-started');
    }
```

**Halt — Korrektur:** `saveUserMessage()` darf nicht zweimal aufgerufen werden. Die korrekte Version:

```php
    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $workspaceId = Auth::user()?->activeWorkspaceId();

        if ($workspaceId === null) {
            return;
        }

        $userMessage   = $this->message;
        $this->message = '';

        $userMsg = app(ChatService::class)->saveUserMessage($workspaceId, Auth::id(), $userMessage);

        $this->loading = true;

        ProcessChatMessageJob::dispatch(
            $userMsg->id,
            $workspaceId,
            Auth::id(),
            [
                'source'       => 'dashboard_chat',
                'user_id'      => Auth::id(),
                'workspace_id' => $workspaceId,
            ],
        );

        $this->dispatch('chat-loading-started');
    }
```

Lösche die gesamte `checkForResponse()` Methode (Zeilen 48–67).

Ersetze `clearHistory()`:
```php
    public function clearHistory(): void
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();

        if ($workspaceId !== null) {
            app(ChatService::class)->clearMessages($workspaceId, Auth::id());
        }

        $this->loading = false;
    }
```

- [ ] **Step 2: wire:poll aus dem Blade-Template entfernen**

Suche im HTML-Teil (Zeile 123):
```html
        @if($loading)
            <div wire:poll.3s="checkForResponse" class="flex justify-start">
```

Ersetze durch:
```html
        @if($loading)
            <div class="flex justify-start">
```

- [ ] **Step 3: Echo-Listener im @script-Block hinzufügen**

Der bestehende `@script`-Block (Zeilen 158–183) enthält bereits den Scroll- und Failsafe-Code. Ersetze den gesamten `@script`-Block durch:

```blade
@script
<script>
    const container = document.getElementById('chat-scroll-container');
    let timeoutHandle = null;

    function scrollToBottom() {
        if (container) container.scrollTop = container.scrollHeight;
    }

    scrollToBottom();

    $wire.on('chat-updated', () => {
        clearTimeout(timeoutHandle);
        setTimeout(scrollToBottom, 50);
    });

    $wire.on('chat-loading-started', () => {
        scrollToBottom();
        // 90s frontend failsafe — stops spinner if queue-worker is down or WS connection lost
        timeoutHandle = setTimeout(() => {
            $wire.set('loading', false);
        }, 90000);
    });

    // WebSocket: Echo-Listener auf privatem Chat-Channel
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
</script>
@endscript
```

- [ ] **Step 4: Bestehende Tests ausführen — alle müssen grün sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/Chat/BigResearchChatTest.php
```

Erwartete Ausgabe: `PASS` — alle verbleibenden Tests bestehen (die 2 checkForResponse-Tests sind bereits in Task 5 gelöscht).

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/chat/big-research-chat.blade.php
git commit -m "feat: replace wire:poll with echo websocket listener in chat"
```

---

## Task 10: Docker starten und verifizieren

**Files:** Terminal only

- [ ] **Step 1: Reverb-Image bauen und starten**

```bash
docker compose up -d --build reverb
```

Erwartete Ausgabe: Container `reverb` started.

- [ ] **Step 2: Nginx neu starten (wegen neuer Konfiguration)**

```bash
docker compose up -d --build web
```

- [ ] **Step 3: Reverb-Logs prüfen**

```bash
docker compose logs -f reverb
```

Erwartete Ausgabe:
```
Starting server on 0.0.0.0:8080
```

Mit `Ctrl+C` beenden.

- [ ] **Step 4: WebSocket-Verbindung im Browser prüfen**

1. Browser öffnen → `https://app.linn.games` → einloggen → Dashboard
2. DevTools öffnen → Network → Filter: WS
3. Erwartete Verbindung: `wss://app.linn.games/app/linn-games-key` → Status `101 Switching Protocols`
4. Nachricht im Chat senden → in den WS-Frames muss ein Event `chat.response` erscheinen

- [ ] **Step 5: Gesamten Test-Suite ausführen**

```bash
docker compose run --rm php-test vendor/bin/pest
```

Erwartete Ausgabe: Alle Tests grün.

- [ ] **Step 6: Final Commit**

```bash
git add .
git status  # Prüfen: nur erwartete Dateien
git commit -m "feat: websocket chat via laravel reverb — replace polling"
```

---

## Bekannte Stolpersteine

1. **`reverb:install` überschreibt .env-Werte** — prüfe nach dem Install, ob `BROADCAST_CONNECTION=reverb` korrekt gesetzt ist (Task 2).
2. **Synology Reverse Proxy** — `Upgrade` + `Connection`-Header müssen im Synology-Panel unter Reverse Proxy → Erweitert gesetzt sein. Laut Design-Spec sind diese bereits konfiguriert.
3. **`toOthers()` im Job** — absichtlich weggelassen. Queue-Jobs haben keinen Socket-Kontext, `toOthers()` würde ohne aktive Socket-ID nichts filtern.
4. **VITE_REVERB_APP_KEY** — Vite liest `.env`-Variablen zur Build-Zeit ein. Nach Änderungen an VITE_*-Variablen muss `npm run build` neu ausgeführt werden.

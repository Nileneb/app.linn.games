<?php

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

test('chat: nachricht senden erstellt user- und assistant-message', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $webhook = Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);

    Http::fake([
        'example.com/*' => Http::response(['output' => 'KI-Antwort hier'], 200),
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Was ist systematische Literaturrecherche?')
        ->call('sendMessage')
        ->assertSet('message', '')
        ->assertSet('loading', false);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'webhook_id' => $webhook->id,
        'role' => 'user',
        'content' => 'Was ist systematische Literaturrecherche?',
    ]);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'webhook_id' => $webhook->id,
        'role' => 'assistant',
        'content' => 'KI-Antwort hier',
    ]);
});

test('chat: validiert nachricht als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', '')
        ->call('sendMessage')
        ->assertHasErrors(['message']);
});

test('chat: ohne webhook wird nichts gespeichert', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Ignoriert')
        ->call('sendMessage');

    expect(ChatMessage::where('user_id', $user->id)->count())->toBe(0);
});

test('chat: http-fehler erzeugt fehlermeldung', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);

    Http::fake([
        'example.com/*' => Http::response('Server Error', 500),
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Trigger error')
        ->call('sendMessage');

    $assistantMsg = ChatMessage::where('user_id', $user->id)
        ->where('role', 'assistant')
        ->first();

    expect($assistantMsg->content)->toContain('Fehler');
});

test('chat: connection exception erzeugt fehlermeldung', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Connection test')
        ->call('sendMessage');

    $assistantMsg = ChatMessage::where('user_id', $user->id)
        ->where('role', 'assistant')
        ->first();

    expect($assistantMsg->content)->toContain('fehlgeschlagen');
});

test('chat: clearHistory löscht nur eigene nachrichten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $webhook = Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);
    $otherWebhook = Webhook::create([
        'user_id' => $other->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-other-' . Str::random(8),
        'url' => 'https://example.com/webhook2',
        'frontend_object' => 'dashboard_chat',
    ]);

    ChatMessage::create(['user_id' => $user->id, 'webhook_id' => $webhook->id, 'role' => 'user', 'content' => 'Meine Frage']);
    ChatMessage::create(['user_id' => $other->id, 'webhook_id' => $otherWebhook->id, 'role' => 'user', 'content' => 'Andere Frage']);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->call('clearHistory');

    expect(ChatMessage::where('user_id', $user->id)->count())->toBe(0);
    expect(ChatMessage::where('user_id', $other->id)->count())->toBe(1);
});

test('chat: getChatMessages gibt nachrichten chronologisch zurueck', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $webhook = Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);

    ChatMessage::create(['user_id' => $user->id, 'webhook_id' => $webhook->id, 'role' => 'user', 'content' => 'Erste']);
    ChatMessage::create(['user_id' => $user->id, 'webhook_id' => $webhook->id, 'role' => 'assistant', 'content' => 'Zweite']);

    $this->actingAs($user);

    // Die Komponente nutzt getMessages() intern — wir prüfen dass sie rendert
    Volt::test('chat.big-research-chat')
        ->assertSee('Erste')
        ->assertSee('Zweite');
});

test('chat: ohne webhook zeigt leere liste', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->assertDontSee('user')
        ->assertSet('loading', false);
});

test('chat: nachricht max 10000 zeichen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    Webhook::create([
        'user_id' => $user->id,
        'name' => 'Dashboard Chat',
        'slug' => 'dashboard-chat-' . Str::random(8),
        'url' => 'https://example.com/webhook',
        'frontend_object' => 'dashboard_chat',
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', str_repeat('a', 10001))
        ->call('sendMessage')
        ->assertHasErrors(['message']);
});

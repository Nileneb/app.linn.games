<?php

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.agent_id', 'test-agent-id');
});

test('chat: nachricht senden erstellt user- und assistant-message', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    Http::fake([
        'app.langdock.com/*' => Http::response([
            'content' => 'Das ist eine systematische Literaturrecherche.',
        ], 200),
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Was ist systematische Literaturrecherche?')
        ->call('sendMessage')
        ->assertSet('message', '')
        ->assertSet('loading', false);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'role' => 'user',
        'content' => 'Was ist systematische Literaturrecherche?',
    ]);

    $assistantMsg = ChatMessage::where('user_id', $user->id)
        ->where('role', 'assistant')
        ->first();

    expect($assistantMsg)->not->toBeNull();
    expect($assistantMsg->content)->toBe('Das ist eine systematische Literaturrecherche.');
});

test('chat: validiert nachricht als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', '')
        ->call('sendMessage')
        ->assertHasErrors(['message']);
});

test('chat: http-fehler erzeugt fehlermeldung', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    Http::fake([
        'app.langdock.com/*' => Http::response('Server Error', 500),
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Trigger error')
        ->call('sendMessage')
        ->assertSet('loading', false);

    $assistantMsg = ChatMessage::where('user_id', $user->id)
        ->where('role', 'assistant')
        ->first();

    expect($assistantMsg->content)->toContain('Fehler');
});

test('chat: connection exception erzeugt fehlermeldung', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Connection test')
        ->call('sendMessage')
        ->assertSet('loading', false);

    $assistantMsg = ChatMessage::where('user_id', $user->id)
        ->where('role', 'assistant')
        ->first();

    expect($assistantMsg->content)->toContain('Fehler');
});

test('chat: sendet multi-turn-kontext an agents api', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    ChatMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Hallo']);
    ChatMessage::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'Hi! Wie kann ich helfen?']);

    Http::fake([
        'app.langdock.com/*' => Http::response(['content' => 'Klar, gerne.'], 200),
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Erkläre mir PICO.')
        ->call('sendMessage');

    Http::assertSent(function ($request) {
        $messages = $request->data()['messages'] ?? [];
        return count($messages) >= 3
            && $messages[0]['role'] === 'user'
            && $messages[0]['content'] === 'Hallo'
            && $messages[1]['role'] === 'assistant'
            && $messages[1]['content'] === 'Hi! Wie kann ich helfen?'
            && end($messages)['role'] === 'user'
            && end($messages)['content'] === 'Erkläre mir PICO.';
    });
});

test('chat: clearHistory löscht nur eigene nachrichten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();

    ChatMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Meine Frage']);
    ChatMessage::create(['user_id' => $other->id, 'role' => 'user', 'content' => 'Andere Frage']);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->call('clearHistory');

    expect(ChatMessage::where('user_id', $user->id)->count())->toBe(0);
    expect(ChatMessage::where('user_id', $other->id)->count())->toBe(1);
});

test('chat: getChatMessages gibt nachrichten chronologisch zurueck', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    ChatMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Erste']);
    ChatMessage::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'Zweite']);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->assertSee('Erste')
        ->assertSee('Zweite');
});

test('chat: nachricht max 10000 zeichen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', str_repeat('a', 10001))
        ->call('sendMessage')
        ->assertHasErrors(['message']);
});

<?php

use App\Models\ChatMessage;
use App\Models\User;
use Livewire\Volt\Volt;

test('chat: nachricht senden erstellt user-message und setzt loading', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->ensureDefaultWorkspace();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', 'Was ist systematische Literaturrecherche?')
        ->call('sendMessage')
        ->assertSet('message', '')
        ->assertSet('loading', true);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'role' => 'user',
        'content' => 'Was ist systematische Literaturrecherche?',
    ]);
});

test('chat: validiert nachricht als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', '')
        ->call('sendMessage')
        ->assertHasErrors(['message']);
});

test('chat: finalizeResponse speichert assistant-nachricht und setzt loading false', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('loading', true)
        ->call('finalizeResponse', 'Hi! Wie kann ich helfen?')
        ->assertSet('loading', false);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'role' => 'assistant',
        'content' => 'Hi! Wie kann ich helfen?',
    ]);
});

test('chat: markStreamError speichert fehlernachricht und setzt loading false', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('loading', true)
        ->call('markStreamError', 'Verbindung unterbrochen')
        ->assertSet('loading', false);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'role' => 'assistant',
    ]);
});

test('chat: clearHistory löscht nur eigene nachrichten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();

    $workspace = $user->ensureDefaultWorkspace();
    $otherWorkspace = $other->ensureDefaultWorkspace();

    ChatMessage::create(['user_id' => $user->id,  'workspace_id' => $workspace->id,      'role' => 'user', 'content' => 'Meine Frage']);
    ChatMessage::create(['user_id' => $other->id, 'workspace_id' => $otherWorkspace->id, 'role' => 'user', 'content' => 'Andere Frage']);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')->call('clearHistory');

    expect(ChatMessage::where('user_id', $user->id)->count())->toBe(0);
    expect(ChatMessage::where('user_id', $other->id)->count())->toBe(1);
});

test('chat: getChatMessages gibt nachrichten chronologisch zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    ChatMessage::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'role' => 'user',      'content' => 'Erste']);
    ChatMessage::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'role' => 'assistant', 'content' => 'Zweite']);

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

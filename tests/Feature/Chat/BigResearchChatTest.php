<?php

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\LangdockAgentService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.agent_id', 'test-agent-id');
});

test('chat: nachricht senden erstellt user-message und dispatcht job', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->ensureDefaultWorkspace();

    $this->actingAs($user);

    // Mock LangdockAgentService so no real API call is made
    $this->mock(LangdockAgentService::class, function ($mock) {
        $mock->shouldReceive('call')
            ->once()
            ->andReturn(['content' => 'Mocked AI response']);
    });

    Volt::test('chat.big-research-chat')
        ->set('message', 'Was ist systematische Literaturrecherche?')
        ->call('sendMessage')
        ->assertSet('message', '');

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'role'    => 'user',
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

test('chat: checkForResponse erkennt antwort und setzt loading false', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $userMsg = ChatMessage::create([
        'user_id'      => $user->id,
        'workspace_id' => $workspace->id,
        'role'         => 'user',
        'content'      => 'Hallo',
    ]);

    // Simulate job writing assistant response
    ChatMessage::create([
        'user_id'      => $user->id,
        'workspace_id' => $workspace->id,
        'role'         => 'assistant',
        'content'      => 'Hi! Wie kann ich helfen?',
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('loading', true)
        ->set('pendingUserMsgId', $userMsg->id)
        ->call('checkForResponse')
        ->assertSet('loading', false)
        ->assertSet('pendingUserMsgId', null);
});

test('chat: checkForResponse tut nichts wenn noch keine antwort', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $userMsg = ChatMessage::create([
        'user_id'      => $user->id,
        'workspace_id' => $workspace->id,
        'role'         => 'user',
        'content'      => 'Noch keine Antwort',
    ]);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('loading', true)
        ->set('pendingUserMsgId', $userMsg->id)
        ->call('checkForResponse')
        ->assertSet('loading', true);
});

test('chat: clearHistory löscht nur eigene nachrichten', function () {
    $user  = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();

    $workspace      = $user->ensureDefaultWorkspace();
    $otherWorkspace = $other->ensureDefaultWorkspace();

    ChatMessage::create(['user_id' => $user->id,  'workspace_id' => $workspace->id,      'role' => 'user', 'content' => 'Meine Frage']);
    ChatMessage::create(['user_id' => $other->id, 'workspace_id' => $otherWorkspace->id, 'role' => 'user', 'content' => 'Andere Frage']);

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')->call('clearHistory');

    expect(ChatMessage::where('user_id', $user->id)->count())->toBe(0);
    expect(ChatMessage::where('user_id', $other->id)->count())->toBe(1);
});

test('chat: getChatMessages gibt nachrichten chronologisch zurück', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
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

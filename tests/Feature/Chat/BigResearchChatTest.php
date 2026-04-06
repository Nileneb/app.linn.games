<?php

use App\Events\ChatResponseReady;
use App\Jobs\ProcessChatMessageJob;
use App\Models\ChatMessage;
use App\Models\User;
use App\Actions\SendAgentMessage;
use App\Services\LangdockArtifactService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.agent_id', 'test-agent-id');
    Queue::fake();
});

test('chat: nachricht senden erstellt user-message und dispatcht job', function () {
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
        'role'    => 'user',
        'content' => 'Was ist systematische Literaturrecherche?',
    ]);

    Queue::assertPushed(ProcessChatMessageJob::class);
});

test('chat: validiert nachricht als pflichtfeld', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    Volt::test('chat.big-research-chat')
        ->set('message', '')
        ->call('sendMessage')
        ->assertHasErrors(['message']);
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

test('chat: job broadcasted ChatResponseReady nach erfolgreicher antwort', function () {
    Event::fake([ChatResponseReady::class]);

    $this->mock(SendAgentMessage::class, function ($mock) {
        $mock->shouldReceive('execute')->andReturn([
            'success' => true,
            'content' => 'KI-Antwort',
            'raw'     => [],
        ]);
    });

    $this->mock(LangdockArtifactService::class, function ($mock) {
        $mock->shouldReceive('persistFromAgentResponse')->andReturn([
            'display_content' => 'KI-Antwort',
            'path'            => null,
        ]);
    });

    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $userMsg = ChatMessage::create([
        'user_id'      => $user->id,
        'workspace_id' => $workspace->id,
        'role'         => 'user',
        'content'      => 'Hallo',
    ]);

    (new ProcessChatMessageJob(
        $userMsg->id,
        $workspace->id,
        $user->id,
        ['source' => 'dashboard_chat', 'user_id' => $user->id, 'workspace_id' => $workspace->id],
    ))->handle();

    Event::assertDispatched(ChatResponseReady::class, function ($e) use ($user, $workspace) {
        return $e->userId === $user->id
            && $e->workspaceId === $workspace->id
            && $e->content === 'KI-Antwort';
    });
});

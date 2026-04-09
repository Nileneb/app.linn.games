<?php

use App\Models\ChatMessage;
use App\Models\User;

// ---------------------------------------------------------------------------
// ChatMessage::historyFor()
// ---------------------------------------------------------------------------

test('historyFor: gibt die letzten N nachrichten chronologisch zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    // 5 Nachrichten anlegen, zeitlich gestaffelt
    foreach (range(1, 5) as $i) {
        ChatMessage::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => $i % 2 === 0 ? 'assistant' : 'user',
            'content' => "Nachricht $i",
            'created_at' => now()->addSeconds($i),
        ]);
    }

    $history = ChatMessage::historyFor($workspace->id, $user->id, limit: 3);

    expect($history)->toHaveCount(3);

    // Die letzten 3 (Nachrichten 3, 4, 5) müssen chronologisch geordnet sein
    expect($history[0]['content'])->toBe('Nachricht 3');
    expect($history[1]['content'])->toBe('Nachricht 4');
    expect($history[2]['content'])->toBe('Nachricht 5');
});

test('historyFor: überschreitet das limit nicht, auch wenn mehr nachrichten vorhanden', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    foreach (range(1, 30) as $i) {
        ChatMessage::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'user',
            'content' => "Nachricht $i",
            'created_at' => now()->addSeconds($i),
        ]);
    }

    $history = ChatMessage::historyFor($workspace->id, $user->id, limit: 10);

    expect($history)->toHaveCount(10);
    expect($history[0]['content'])->toBe('Nachricht 21');
    expect($history[9]['content'])->toBe('Nachricht 30');
});

test('historyFor: schließt nachrichten mit null-content aus', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    ChatMessage::create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'role' => 'user',
        'content' => 'Gültige Nachricht',
        'created_at' => now()->addSeconds(1),
    ]);

    // Direkt per DB einfügen, da fillable kein null-content erlaubt
    \Illuminate\Support\Facades\DB::table('chat_messages')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'role' => 'assistant',
        'content' => null,
        'created_at' => now()->addSeconds(2),
    ]);

    $history = ChatMessage::historyFor($workspace->id, $user->id, limit: 10);

    expect($history)->toHaveCount(1);
    expect($history[0]['content'])->toBe('Gültige Nachricht');
});

test('historyFor: isoliert nachrichten nach workspace und user', function () {
    $userA = User::factory()->withoutTwoFactor()->create();
    $userB = User::factory()->withoutTwoFactor()->create();
    $workspaceA = $userA->ensureDefaultWorkspace();
    $workspaceB = $userB->ensureDefaultWorkspace();

    ChatMessage::create([
        'user_id' => $userA->id,
        'workspace_id' => $workspaceA->id,
        'role' => 'user',
        'content' => 'Nachricht von A',
    ]);
    ChatMessage::create([
        'user_id' => $userB->id,
        'workspace_id' => $workspaceB->id,
        'role' => 'user',
        'content' => 'Nachricht von B',
    ]);

    $historyA = ChatMessage::historyFor($workspaceA->id, $userA->id);
    $historyB = ChatMessage::historyFor($workspaceB->id, $userB->id);

    expect($historyA)->toHaveCount(1)->and($historyA[0]['content'])->toBe('Nachricht von A');
    expect($historyB)->toHaveCount(1)->and($historyB[0]['content'])->toBe('Nachricht von B');
});

test('historyFor: gibt korrektes role+content format zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    ChatMessage::create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'role' => 'assistant',
        'content' => 'Ich bin der Assistent',
    ]);

    $history = ChatMessage::historyFor($workspace->id, $user->id);

    expect($history[0])->toMatchArray(['role' => 'assistant', 'content' => 'Ich bin der Assistent']);
    expect($history[0])->toHaveKeys(['role', 'content']);
    expect(array_keys($history[0]))->toBe(['role', 'content']);
});

// ---------------------------------------------------------------------------
// ChatMessage::saveAssistantReply()
// ---------------------------------------------------------------------------

test('saveAssistantReply: persistiert eine assistant-nachricht korrekt', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $msg = ChatMessage::saveAssistantReply($workspace->id, $user->id, 'KI-Antwort');

    expect($msg->role)->toBe('assistant');
    expect($msg->content)->toBe('KI-Antwort');
    expect($msg->workspace_id)->toBe($workspace->id);
    expect($msg->user_id)->toBe($user->id);

    $this->assertDatabaseHas('chat_messages', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'assistant',
        'content' => 'KI-Antwort',
    ]);
});

test('saveAssistantReply: gibt eine chatmessage-instanz zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->ensureDefaultWorkspace();

    $msg = ChatMessage::saveAssistantReply($workspace->id, $user->id, 'Test');

    expect($msg)->toBeInstanceOf(ChatMessage::class);
    expect($msg->id)->not->toBeNull();
});

<?php

use App\Actions\SendAgentMessage;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\WorkspaceUser;
use App\Services\AgentPromptBuilder;
use Livewire\Volt\Volt;

// Stellt sicher, dass triggerAgent() den Kontext korrekt an SendAgentMessage übergibt (Issue #154).
// AgentPromptBuilder wird gemockt, da er DB-Abfragen macht, die hier irrelevant sind.

test('triggerAgent übergibt projekt_id, user_id, workspace_id und phase_nr an SendAgentMessage', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    // AgentPromptBuilder mocken, um DB-Abfragen zu umgehen
    $this->mock(AgentPromptBuilder::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('System Prompt');
        $mock->shouldReceive('buildUserPrompt')->andReturn('User Prompt');
    });

    // Kontext-Capture via andReturnUsing (withArgs-Closure hat Scope-Probleme in verschachtelten Mocks)
    $capturedContext = null;

    $this->mock(SendAgentMessage::class, function ($mock) use (&$capturedContext) {
        $mock->shouldReceive('execute')
            ->andReturnUsing(function () use (&$capturedContext) {
                $args = func_get_args();
                $capturedContext = $args[3] ?? null;
                return ['success' => false, 'content' => 'test-mocked'];
            });
    });

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('triggerAgent', 1)
        ->assertDispatched('notify');

    // Kontext muss die vier Pflichtfelder enthalten
    expect($capturedContext)->not->toBeNull('execute() wurde nicht aufgerufen')
        ->and($capturedContext['projekt_id'])->toBe($projekt->id)
        ->and($capturedContext['user_id'])->toBe($user->id)
        ->and($capturedContext['workspace_id'])->toBe($projekt->workspace_id)
        ->and($capturedContext['phase_nr'])->toBe(1);
});

test('triggerAgent verwendet auth()->id() als user_id, nicht den Projekt-Ersteller', function () {
    // Projekt-Ersteller und aktiver Nutzer sind verschiedene Personen
    $projektOwner = User::factory()->withoutTwoFactor()->create();
    $aktuellerUser = User::factory()->withoutTwoFactor()->create();

    $projekt = Projekt::factory()->create(['user_id' => $projektOwner->id]);

    // Workspace-Zugang für aktiven Nutzer via Factory gewähren (workspace_users hat UUID-id)
    WorkspaceUser::factory()->create([
        'workspace_id' => $projekt->workspace_id,
        'user_id'      => $aktuellerUser->id,
        'role'         => 'editor',
    ]);

    $this->actingAs($aktuellerUser);

    $this->mock(AgentPromptBuilder::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('System Prompt');
        $mock->shouldReceive('buildUserPrompt')->andReturn('User Prompt');
    });

    $capturedUserId = null;

    $this->mock(SendAgentMessage::class, function ($mock) use (&$capturedUserId) {
        $mock->shouldReceive('execute')
            ->andReturnUsing(function () use (&$capturedUserId) {
                $args = func_get_args();
                $context = $args[3] ?? [];
                $capturedUserId = $context['user_id'] ?? null;
                return ['success' => false, 'content' => 'test-mocked'];
            });
    });

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('triggerAgent', 1)
        ->assertDispatched('notify');

    // user_id muss der aktive Nutzer sein, nicht der Projekt-Ersteller
    expect($capturedUserId)->not->toBeNull('execute() wurde nicht aufgerufen')
        ->and($capturedUserId)->toBe($aktuellerUser->id)
        ->and($capturedUserId)->not->toBe($projektOwner->id);
});

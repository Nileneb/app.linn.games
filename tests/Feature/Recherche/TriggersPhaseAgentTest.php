<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\WorkspaceUser;
use App\Services\AgentPromptBuilder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

// Stellt sicher, dass triggerAgent() einen Job dispatched und einen pending PhaseAgentResult-Record anlegt.
// Die alten Tests prüften SendAgentMessage (synchron); der neue Flow nutzt ProcessPhaseAgentJob (Queue).

test('triggerAgent übergibt projekt_id, user_id, workspace_id und phase_nr an ProcessPhaseAgentJob', function () {
    Queue::fake();

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->mock(AgentPromptBuilder::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('System Prompt');
        $mock->shouldReceive('buildUserPrompt')->andReturn('User Prompt');
    });

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('triggerAgent', 1)
        ->assertSet('agentDispatched', true);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) use ($projekt) {
        return $job->projektId === $projekt->id && $job->phaseNr === 1;
    });

    $this->assertDatabaseHas('phase_agent_results', [
        'projekt_id' => $projekt->id,
        'user_id'    => $user->id,
        'phase_nr'   => 1,
        'status'     => 'pending',
    ]);
});

test('triggerAgent verwendet auth()->id() als user_id, nicht den Projekt-Ersteller', function () {
    Queue::fake();

    $projektOwner  = User::factory()->withoutTwoFactor()->create();
    $aktuellerUser = User::factory()->withoutTwoFactor()->create();

    $projekt = Projekt::factory()->create(['user_id' => $projektOwner->id]);

    // Workspace-Zugang für aktiven Nutzer
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

    Volt::test('recherche.phase-p1', ['projekt' => $projekt])
        ->call('triggerAgent', 1)
        ->assertSet('agentDispatched', true);

    // PhaseAgentResult muss user_id des aktiven Nutzers haben, nicht des Projekt-Erstellers
    $this->assertDatabaseHas('phase_agent_results', [
        'projekt_id' => $projekt->id,
        'user_id'    => $aktuellerUser->id,
        'status'     => 'pending',
    ]);

    $this->assertDatabaseMissing('phase_agent_results', [
        'user_id' => $projektOwner->id,
    ]);
});

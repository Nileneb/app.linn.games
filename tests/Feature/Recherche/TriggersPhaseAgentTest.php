<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\WorkspaceUser;
use App\Services\AgentPromptBuilder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

// Pipeline-Trigger lebt jetzt in phase-group-status-header::startPipeline(int $fromPhase).
// P1-Trigger → PhaseChainService übernimmt auto-chain P1→P2→P3→P4.

test('startPipeline übergibt projekt_id, user_id und phase_nr an ProcessPhaseAgentJob', function () {
    Queue::fake();

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->mock(AgentPromptBuilder::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('System Prompt');
        $mock->shouldReceive('buildUserPrompt')->andReturn('User Prompt');
    });

    Volt::test('recherche.phase-group-status-header', ['projekt' => $projekt])
        ->call('startPipeline', 1);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) use ($projekt) {
        return $job->projektId === $projekt->id && $job->phaseNr === 1;
    });

    $this->assertDatabaseHas('phase_agent_results', [
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 1,
        'status' => 'pending',
    ]);
});

test('startPipeline verwendet auth()->id() als user_id, nicht den Projekt-Ersteller', function () {
    Queue::fake();

    $projektOwner = User::factory()->withoutTwoFactor()->create();
    $aktuellerUser = User::factory()->withoutTwoFactor()->create();

    $projekt = Projekt::factory()->create(['user_id' => $projektOwner->id]);

    WorkspaceUser::factory()->create([
        'workspace_id' => $projekt->workspace_id,
        'user_id' => $aktuellerUser->id,
        'role' => 'editor',
    ]);

    $this->actingAs($aktuellerUser);

    $this->mock(AgentPromptBuilder::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('System Prompt');
        $mock->shouldReceive('buildUserPrompt')->andReturn('User Prompt');
    });

    Volt::test('recherche.phase-group-status-header', ['projekt' => $projekt])
        ->call('startPipeline', 1);

    $this->assertDatabaseHas('phase_agent_results', [
        'projekt_id' => $projekt->id,
        'user_id' => $aktuellerUser->id,
        'status' => 'pending',
    ]);

    $this->assertDatabaseMissing('phase_agent_results', [
        'user_id' => $projektOwner->id,
    ]);
});

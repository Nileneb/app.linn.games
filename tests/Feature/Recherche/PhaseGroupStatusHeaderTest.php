<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Phase;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->projekt = Projekt::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    // Create all 8 phases
    foreach (range(1, 8) as $phaseNr) {
        Phase::factory()->create([
            'projekt_id' => $this->projekt->id,
            'phase_nr' => $phaseNr,
            'status' => 'offen',
        ]);
    }

    $this->actingAs($this->user);
});

test('phase group status header renders with 3 groups', function () {
    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    $response->assertSeeText('Scoping');
    $response->assertSeeText('Recherche');
    $response->assertSeeText('Synthese');
});

test('header displays all 8 phases with status icons', function () {
    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    // Check all phases are visible
    foreach (range(1, 8) as $phaseNr) {
        $response->assertSeeText("P{$phaseNr}");
    }
});

test('phase status icons display correctly', function () {
    // Set P1 to completed
    $this->projekt->phasen()->where('phase_nr', 1)->update(['status' => 'abgeschlossen']);

    // Set P2 to in_bearbeitung
    $this->projekt->phasen()->where('phase_nr', 2)->update(['status' => 'in_bearbeitung']);

    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    // Completed phase should have green background
    $response->assertSee('bg-green-100', escape: false);

    // In progress phase should have amber color with animation
    $response->assertSee('animate-pulse', escape: false);
});

test('clicking group button dispatches phase agent job', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    $component->call('startGroupAgent', 1);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->projektId === $this->projekt->id && $job->phaseNr === 1;
    });
});

test('button disables immediately after click', function () {
    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    $component->assertSet('agentRunning', false);

    $component->call('startGroupAgent', 1);

    $component->assertSet('agentRunning', true);
    $component->assertSet('runningGroupNumber', 1);
});

test('button re-enables when agent job completes', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    // Start via full pipeline so pipelineStarted is true
    $component->call('startFullPipeline');
    $component->assertSet('agentRunning', true);

    // Create completed results for all 3 groups (8 phases)
    foreach (range(1, 8) as $phaseNr) {
        PhaseAgentResult::factory()->create([
            'projekt_id' => $this->projekt->id,
            'phase_nr' => $phaseNr,
            'status' => 'completed',
        ]);
    }

    // Check status — pipeline should detect all groups done
    $component->call('checkAgentStatus');

    $component->assertSet('agentRunning', false);
    $component->assertSet('runningGroupNumber', null);
});

test('group 2 dispatches P4 with search_agent config', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 4,
    ]);

    $component->call('startGroupAgent', 2);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->phaseNr === 4 && $job->agentConfigKey === 'search_agent';
    });
});

test('group 3 dispatches P7 with review_agent config', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 7,
    ]);

    $component->call('startGroupAgent', 3);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->phaseNr === 7 && $job->agentConfigKey === 'review_agent';
    });
});

test('different group buttons can be clicked independently', function () {
    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
        'currentPhaseNr' => 1,
    ]);

    // Start group 1
    $component->call('startGroupAgent', 1);
    $component->assertSet('runningGroupNumber', 1);

    // Start group 2 (overrides the running group)
    $component->call('startGroupAgent', 2);
    $component->assertSet('agentRunning', true);
    $component->assertSet('runningGroupNumber', 2);
});

<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Phase;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentPromptBuilder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->projekt = Projekt::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    foreach (range(1, 8) as $phaseNr) {
        Phase::factory()->create([
            'projekt_id' => $this->projekt->id,
            'phase_nr' => $phaseNr,
            'status' => 'offen',
        ]);
    }

    $this->actingAs($this->user);

    $this->mock(AgentPromptBuilder::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('System');
        $mock->shouldReceive('buildUserPrompt')->andReturn('User');
    });
});

test('phase group status header renders segment labels', function () {
    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $response->assertSeeText('P1–P4');
    $response->assertSeeText('P5–P8');
});

test('header displays all 8 phases', function () {
    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    foreach (range(1, 8) as $phaseNr) {
        $response->assertSeeText("P{$phaseNr}");
    }
});

test('phase status icons display correctly for completed agent result', function () {
    // P1 agent result = completed → green bar
    PhaseAgentResult::factory()->create([
        'projekt_id' => $this->projekt->id,
        'phase_nr' => 1,
        'status' => 'completed',
    ]);

    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $response->assertSee('bg-green-100', escape: false);
});

test('pending agent result shows spinner', function () {
    PhaseAgentResult::factory()->create([
        'projekt_id' => $this->projekt->id,
        'phase_nr' => 1,
        'status' => 'pending',
    ]);

    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $response->assertSee('animate-spin', escape: false);
    $response->assertSeeText('KI läuft im Hintergrund');
});

test('startPipeline(1) dispatches P1 job with scoping_mapping_agent', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $component->call('startPipeline', 1);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->projektId === $this->projekt->id
            && $job->phaseNr === 1
            && $job->agentConfigKey === 'scoping_mapping_agent';
    });
});

test('startPipeline(1) creates pending PhaseAgentResult', function () {
    Queue::fake();

    Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ])->call('startPipeline', 1);

    $this->assertDatabaseHas('phase_agent_results', [
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'status' => 'pending',
    ]);
});

test('P5 start button appears after P4 agent completes', function () {
    PhaseAgentResult::factory()->create([
        'projekt_id' => $this->projekt->id,
        'phase_nr' => 4,
        'status' => 'completed',
    ]);

    $response = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $response->assertSeeText('Analyse P5–P8 starten');
});

test('startPipeline(4) dispatches P4 with search_agent config', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $component->call('startPipeline', 4);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->phaseNr === 4 && $job->agentConfigKey === 'search_agent';
    });
});

test('startPipeline(7) dispatches P7 with review_agent config', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $component->call('startPipeline', 7);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->phaseNr === 7 && $job->agentConfigKey === 'review_agent';
    });
});

test('startPipeline(5) dispatches P5 with review_agent config', function () {
    Queue::fake();

    $component = Volt::test('recherche.phase-group-status-header', [
        'projekt' => $this->projekt,
    ]);

    $component->call('startPipeline', 5);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        return $job->phaseNr === 5 && $job->agentConfigKey === 'review_agent';
    });
});

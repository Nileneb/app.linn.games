<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PhaseChainService;
use Illuminate\Support\Facades\Queue;

function makeChainProjekt(): Projekt
{
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Test']);
    return Projekt::factory()->create([
        'user_id'          => $user->id,
        'workspace_id'     => $workspace->id,
        'forschungsfrage'  => 'Testfrage',
    ]);
}

test('maybeDispatchNext dispatcht den nächsten job wenn konfiguriert', function () {
    Queue::fake();
    config(['services.langdock.scoping_mapping_agent' => 'uuid-test-agent']);

    $projekt = makeChainProjekt();

    app(PhaseChainService::class)->maybeDispatchNext($projekt, 1);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) use ($projekt) {
        return $job->projektId === $projekt->id
            && $job->phaseNr === 2
            && $job->agentConfigKey === 'scoping_mapping_agent';
    });
});

test('maybeDispatchNext dispatcht nichts wenn phase nicht in chain', function () {
    Queue::fake();

    $projekt = makeChainProjekt();

    // P4 → P5 is intentionally not in the chain
    app(PhaseChainService::class)->maybeDispatchNext($projekt, 4);

    Queue::assertNothingPushed();
});

test('maybeDispatchNext dispatcht nichts wenn agent nicht konfiguriert', function () {
    Queue::fake();
    config(['services.langdock.scoping_mapping_agent' => null]);

    $projekt = makeChainProjekt();

    app(PhaseChainService::class)->maybeDispatchNext($projekt, 1);

    Queue::assertNothingPushed();
});

test('maybeDispatchNext dispatcht nichts nach letzter phase', function () {
    Queue::fake();

    $projekt = makeChainProjekt();

    app(PhaseChainService::class)->maybeDispatchNext($projekt, 8);

    Queue::assertNothingPushed();
});

test('maybeDispatchNext enthält vorherige phase-ergebnisse im context', function () {
    Queue::fake();
    config(['services.langdock.scoping_mapping_agent' => 'uuid-test-agent']);

    $projekt = makeChainProjekt();

    PhaseAgentResult::create([
        'projekt_id'       => $projekt->id,
        'user_id'          => $projekt->user_id,
        'phase_nr'         => 1,
        'agent_config_key' => 'scoping_mapping_agent',
        'status'           => 'completed',
        'content'          => 'Ergebnis Phase 1',
    ]);

    app(PhaseChainService::class)->maybeDispatchNext($projekt, 1);

    Queue::assertPushed(ProcessPhaseAgentJob::class, function ($job) {
        $messageContent = $job->messages[0]['content'] ?? '';
        return str_contains($messageContent, 'Ergebnis Phase 1');
    });
});

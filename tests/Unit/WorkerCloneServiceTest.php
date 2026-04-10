<?php

use App\Exceptions\CloneLimitExceededException;
use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkerCloneService;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('shouldClone() gibt false zurück wenn PhaseAgentResult completed ist', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $result    = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'status'     => 'completed',
    ]);

    expect(app(WorkerCloneService::class)->shouldClone($result, $projekt))->toBeFalse();
});

test('shouldClone() gibt true zurück nach 3 failed Ergebnissen', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(3)->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'failed',
    ]);

    $lastResult = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'failed',
    ]);

    expect(app(WorkerCloneService::class)->shouldClone($lastResult, $projekt))->toBeTrue();
});

test('clone() dispatcht neuen ProcessPhaseAgentJob', function () {
    Queue::fake();

    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'pro']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $result    = PhaseAgentResult::factory()->create([
        'projekt_id'       => $projekt->id,
        'phase_nr'         => 1,
        'status'           => 'failed',
    ]);

    app(WorkerCloneService::class)->clone($result, $projekt, 'retry');

    Queue::assertPushed(ProcessPhaseAgentJob::class);
});

test('clone() wirft CloneLimitExceededException bei free tier mit pending job', function () {
    Queue::fake();

    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 2,
        'status'     => 'pending',
    ]);

    $failedResult = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'failed',
    ]);

    expect(fn () => app(WorkerCloneService::class)->clone($failedResult, $projekt, 'retry'))
        ->toThrow(CloneLimitExceededException::class);
});

<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PhaseChainService;

test('detectStuck() gibt false zurück wenn Phase completed ist', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'completed',
    ]);

    expect(app(PhaseChainService::class)->detectStuck($projekt, 1))->toBeFalse();
});

test('detectStuck() gibt true zurück bei 3+ failed Ergebnissen', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(3)->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 2,
        'status'     => 'failed',
    ]);

    expect(app(PhaseChainService::class)->detectStuck($projekt, 2))->toBeTrue();
});

test('detectStuck() gibt false zurück wenn noch kein Ergebnis vorhanden', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    expect(app(PhaseChainService::class)->detectStuck($projekt, 3))->toBeFalse();
});

<?php

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Exceptions\CloneLimitExceededException;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditService;

test('free tier: erlaubt 0 pending → kein Fehler', function () {
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    app(CreditService::class)->checkCloneLimit($workspace);
    expect(true)->toBeTrue();
});

test('free tier: wirft Exception bei 1 pending PhaseAgentResult', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'status' => 'pending',
    ]);

    expect(fn () => app(CreditService::class)->checkCloneLimit($workspace))
        ->toThrow(CloneLimitExceededException::class);
});

test('pro tier: erlaubt bis zu 3 pending', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'pro']);
    $projekt = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(3)->create([
        'projekt_id' => $projekt->id,
        'status' => 'pending',
    ]);

    expect(fn () => app(CreditService::class)->checkCloneLimit($workspace))
        ->toThrow(CloneLimitExceededException::class);
});

test('enterprise tier: kein Limit', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'enterprise']);
    $projekt = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(20)->create([
        'projekt_id' => $projekt->id,
        'status' => 'pending',
    ]);

    app(CreditService::class)->checkCloneLimit($workspace);
    expect(true)->toBeTrue();
});

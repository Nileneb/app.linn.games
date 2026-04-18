<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Services\InsufficientCreditsException;

test('markOutOfCredits setzt status auf out_of_credits', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $result = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 1,
        'status' => 'pending',
        'content' => 'In Bearbeitung',
    ]);

    $result->markOutOfCredits();

    $fresh = $result->fresh();
    expect($fresh->status)->toBe('out_of_credits')
        ->and($fresh->content)->toBeNull()
        ->and($fresh->error_message)->toContain('Guthaben');
});

test('markOutOfCredits überschreibt bestehenden content', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $result = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 2,
        'status' => 'pending',
        'content' => 'Teilweise generierter Inhalt',
    ]);

    $result->markOutOfCredits();

    expect($result->fresh()->content)->toBeNull();
});

test('InsufficientCreditsException existiert und ist throwbar', function () {
    expect(class_exists(InsufficientCreditsException::class))->toBeTrue();

    $ex = new InsufficientCreditsException('Test');
    expect($ex)->toBeInstanceOf(\Throwable::class);
});

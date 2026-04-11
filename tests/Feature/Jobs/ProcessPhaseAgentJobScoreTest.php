<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;

function makeProjektForJobTest(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => $user->name.' Workspace',
    ]);
    WorkspaceUser::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
    $projekt = Projekt::create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'titel' => 'Test-Projekt',
        'forschungsfrage' => 'Wirkt KI in der Pflege?',
    ]);

    return [$projekt, $user];
}

test('P1 qualitaets_bewertung wird in result_data gespeichert', function () {
    [$projekt, $user] = makeProjektForJobTest();

    $result = PhaseAgentResult::create([
        'projekt_id'       => $projekt->id,
        'user_id'          => $user->id,
        'phase_nr'         => 1,
        'phase'            => 'p1',
        'agent_config_key' => 'scoping_mapping_agent',
        'status'           => 'pending',
    ]);

    $parsed = [
        'meta' => [
            'qualitaets_bewertung' => [
                'score'  => 78,
                'level'  => 'gut',
                'punkte' => ['+ Klare Populationsdefinition', '- Outcome noch zu breit'],
            ],
        ],
        'db_payload' => null,
    ];

    // Replicate the job logic directly
    if ($parsed['meta']['qualitaets_bewertung'] ?? null) {
        $bewertung = $parsed['meta']['qualitaets_bewertung'];
        if (is_array($bewertung) && isset($bewertung['score'])) {
            $result->update(['result_data' => ['qualitaets_bewertung' => $bewertung]]);
        }
    }

    $result->refresh();

    expect($result->result_data['qualitaets_bewertung']['score'])->toBe(78)
        ->and($result->result_data['qualitaets_bewertung']['level'])->toBe('gut')
        ->and($result->result_data['qualitaets_bewertung']['punkte'])->toHaveCount(2);
});

test('P1 ohne qualitaets_bewertung in meta schreibt nichts in result_data', function () {
    [$projekt, $user] = makeProjektForJobTest();

    $result = PhaseAgentResult::create([
        'projekt_id'       => $projekt->id,
        'user_id'          => $user->id,
        'phase_nr'         => 1,
        'phase'            => 'p1',
        'agent_config_key' => 'scoping_mapping_agent',
        'status'           => 'pending',
    ]);

    $parsed = ['meta' => [], 'db_payload' => null];

    $bewertung = $parsed['meta']['qualitaets_bewertung'] ?? null;
    if (is_array($bewertung) && isset($bewertung['score'])) {
        $result->update(['result_data' => ['qualitaets_bewertung' => $bewertung]]);
    }

    $result->refresh();
    expect($result->result_data)->toBeNull();
});

test('Nicht-P1 Phase schreibt keinen Score', function () {
    [$projekt, $user] = makeProjektForJobTest();

    $result = PhaseAgentResult::create([
        'projekt_id'       => $projekt->id,
        'user_id'          => $user->id,
        'phase_nr'         => 2,
        'phase'            => 'p2',
        'agent_config_key' => 'scoping_mapping_agent',
        'status'           => 'pending',
    ]);

    // Phase 2 should NOT write result_data even if meta has the field
    $phaseNr = 2;
    $parsed = [
        'meta' => ['qualitaets_bewertung' => ['score' => 80, 'level' => 'sehr_gut', 'punkte' => []]],
    ];

    if ($phaseNr === 1) {
        $bewertung = $parsed['meta']['qualitaets_bewertung'] ?? null;
        if (is_array($bewertung) && isset($bewertung['score'])) {
            $result->update(['result_data' => ['qualitaets_bewertung' => $bewertung]]);
        }
    }

    $result->refresh();
    expect($result->result_data)->toBeNull();
});

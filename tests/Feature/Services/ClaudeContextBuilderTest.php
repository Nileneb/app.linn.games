<?php

use App\Models\Recherche\P1Kriterium;
use App\Models\Recherche\P2Cluster;
use App\Models\Recherche\P3Datenbankmatrix;
use App\Models\Recherche\P4Suchstring;
use App\Models\Recherche\P5ScreeningEntscheidung;
use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\P6Qualitaetsbewertung;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use App\Services\ClaudeContextBuilder;

function makeProjektWithWorkspace(): array
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

    return [$projekt, $workspace, $user];
}

test('build gibt markdown-block mit forschungsfrage zurück', function () {
    [$projekt, , $user] = makeProjektWithWorkspace();

    $context = [
        'projekt_id' => $projekt->id,
        'workspace_id' => $projekt->workspace_id,
        'user_id' => $user->id,
        'phase_nr' => 1,
    ];

    $result = app(ClaudeContextBuilder::class)->build($context);

    expect($result)
        ->toContain('Wirkt KI in der Pflege?')
        ->toContain('Projektkontext')
        ->toContain('Phase: P1');
});

test('build gibt leeren string zurück wenn kein projekt_id', function () {
    $result = app(ClaudeContextBuilder::class)->build([]);
    expect($result)->toBe('');
});

test('build gibt leeren string zurück wenn projekt nicht gefunden', function () {
    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => '00000000-0000-0000-0000-000000000000',
    ]);
    expect($result)->toBe('');
});

test('build enthält structured_output hinweis wenn gesetzt', function () {
    [$projekt, , $user] = makeProjektWithWorkspace();

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 1,
        'structured_output' => true,
        'agent_config_key' => 'scoping_mapping_agent',
    ]);

    expect($result)
        ->toContain('Output-Anforderung')
        ->toContain('db_payload')
        ->toContain('Pflichtstruktur');
});

test('build phase 2 enthält p1Kriterien wenn vorhanden', function () {
    [$projekt] = makeProjektWithWorkspace();

    P1Kriterium::create([
        'projekt_id' => $projekt->id,
        'kriterium_typ' => 'einschluss',
        'beschreibung' => 'RCT-Studien',
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 2,
    ]);

    expect($result)
        ->toContain('P1-Kriterien')
        ->toContain('einschluss')
        ->toContain('RCT-Studien');
});

test('build phase 3 enthält p2Cluster wenn vorhanden', function () {
    [$projekt] = makeProjektWithWorkspace();

    P2Cluster::create([
        'projekt_id' => $projekt->id,
        'cluster_id' => 'C1',
        'cluster_label' => 'KI-Assistenz',
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 3,
    ]);

    expect($result)
        ->toContain('P2-Cluster')
        ->toContain('KI-Assistenz');
});

test('build phase 5 enthält treffer-count wenn treffer vorhanden', function () {
    [$projekt] = makeProjektWithWorkspace();

    P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-001',
        'titel' => 'Teststudie',
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 5,
    ]);

    expect($result)->toContain('1 importierte Treffer');
});

test('build phase 7 enthält p6Qualitaetsbewertungen wenn vorhanden', function () {
    [$projekt] = makeProjektWithWorkspace();

    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-002',
        'titel' => 'Bewertete Studie',
    ]);

    P6Qualitaetsbewertung::create([
        'treffer_id' => $treffer->id,
        'studientyp' => 'RCT',
        'rob_tool' => 'RoB2',
        'gesamturteil' => 'niedrig',
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 7,
    ]);

    expect($result)
        ->toContain('P6-Qualitätsbewertungen')
        ->toContain('1 Studien bewertet');
});

test('build phase 2 ohne kriterien enthält keinen p1Kriterien-Block', function () {
    [$projekt] = makeProjektWithWorkspace();

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 2,
    ]);

    expect($result)->not->toContain('P1-Kriterien');
});

test('build enthält p3 datenbanken bei phase 4', function () {
    [$projekt] = makeProjektWithWorkspace();

    P3Datenbankmatrix::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'disziplin' => 'Medizin',
        'empfohlen' => true,
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 4,
    ]);

    expect($result)
        ->toContain('P3-Datenbanken')
        ->toContain('PubMed')
        ->toContain('Medizin')
        ->toContain('Ja');
});

test('build enthält p4 suchstrings bei phase 5', function () {
    [$projekt] = makeProjektWithWorkspace();

    P4Suchstring::create([
        'projekt_id' => $projekt->id,
        'datenbank' => 'PubMed',
        'suchstring' => '("artificial intelligence" OR "machine learning") AND nursing',
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 5,
    ]);

    expect($result)
        ->toContain('P4-Suchstrings')
        ->toContain('PubMed')
        ->toContain('artificial intelligence');
});

test('build phase 6 enthält screening count ohne relation-guard', function () {
    [$projekt] = makeProjektWithWorkspace();

    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'REC-SCREEN-01',
        'titel' => 'Eingeschlossene Studie',
    ]);

    P5ScreeningEntscheidung::create([
        'treffer_id' => $treffer->id,
        'level' => 'L1_titel_abstract',
        'entscheidung' => 'eingeschlossen',
    ]);

    $result = app(ClaudeContextBuilder::class)->build([
        'projekt_id' => $projekt->id,
        'phase_nr' => 6,
    ]);

    expect($result)
        ->toContain('P5-Screening')
        ->toContain('Eingeschlossene Treffer');
});

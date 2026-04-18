<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Services\ProjectExportService;

test('generateLaTeX rendert generic template', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 1,
        'status' => 'completed',
        'content' => 'Forschungsfrage: Teststudie',
    ]);

    $latex = app(ProjectExportService::class)->generateLaTeX($projekt, 'generic');

    expect($latex)->toContain('\documentclass')
        ->and($latex)->toContain('\begin{document}')
        ->and($latex)->toContain('Forschungsfrage');
});

test('generateLaTeX rendert apa template', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $latex = app(ProjectExportService::class)->generateLaTeX($projekt, 'apa');

    expect($latex)->toContain('apa7');
});

test('generateLaTeX rendert ieee template', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $latex = app(ProjectExportService::class)->generateLaTeX($projekt, 'ieee');

    expect($latex)->toContain('IEEEtran');
});

test('generateLaTeX fällt auf generic zurück bei unbekanntem style', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $latex = app(ProjectExportService::class)->generateLaTeX($projekt, 'invalid-style');

    expect($latex)->toContain('\documentclass')
        ->and($latex)->not->toContain('apa7')
        ->and($latex)->not->toContain('IEEEtran');
});

test('generateLaTeX escaped LaTeX-Sonderzeichen in Inhalten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 1,
        'status' => 'completed',
        'content' => 'Kosten & Nutzen 50% mit _Unterstrich_',
    ]);

    $latex = app(ProjectExportService::class)->generateLaTeX($projekt, 'generic');

    expect($latex)
        ->toContain('\&')
        ->toContain('\%')
        ->toContain('\_');
});

test('generateLaTeX gibt leeres Dokument ohne PhaseAgentResults zurück', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $latex = app(ProjectExportService::class)->generateLaTeX($projekt, 'generic');

    expect($latex)->toContain('\documentclass')
        ->and($latex)->toContain('\end{document}');
});

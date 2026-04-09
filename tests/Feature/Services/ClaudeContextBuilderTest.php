<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ClaudeContextBuilder;

function makeProjektWithWorkspace(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => $user->name.' Workspace',
    ]);
    \App\Models\WorkspaceUser::create([
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
    ]);

    expect($result)->toContain('JSON Envelope v1');
});

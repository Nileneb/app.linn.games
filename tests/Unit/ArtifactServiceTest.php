<?php

use App\Services\ArtifactService;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

test('it stores markdown artifact when always_write_md is enabled', function () {
    Storage::fake();

    $svc = app(ArtifactService::class);

    $r = $svc->persistFromAgentResponse(
        "# Hallo\n\nDas ist ein Test.\n",
        ['workspace_id' => 'w-1'],
        ['always_write_md' => true, 'scope' => 'phase', 'phase_nr' => 8, 'config_key' => 'review_agent', 'basename' => 'final'],
    );

    expect($r['display_content'])->toContain('Hallo')
        ->and($r['stored_paths'])->not->toBeEmpty();

    $first = $r['stored_paths'][0];
    Storage::assertExists($first);
    expect(Storage::get($first))->toContain('Das ist ein Test');
});

test('it parses structured output and stores md_files when present', function () {
    Storage::fake();

    $payload = [
        'meta' => ['projekt_id' => 'p-1', 'workspace_id' => 'w-1', 'user_id' => '1', 'triggerword' => 'report', 'version' => 1],
        'db' => ['bootstrapped' => true, 'loaded' => []],
        'result' => [
            'type' => 'final_report',
            'summary' => "# Summary\n\nOK",
            'data' => [
                'md_files' => [
                    ['path' => 'final-report.md', 'content' => "# Final\n\nDone"],
                ],
            ],
        ],
        'next' => ['route_to' => null, 'reason' => null],
        'warnings' => [],
    ];

    $svc = app(ArtifactService::class);

    $r = $svc->persistFromAgentResponse(
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        ['projekt_id' => 'p-1', 'structured_output' => true],
        ['scope' => 'chat', 'config_key' => 'synthesis_agent', 'basename' => 'chat-1'],
    );

    expect($r['display_content'])->toContain('Summary')
        ->and($r['stored_paths'])->toHaveCount(2);

    Storage::assertExists($r['stored_paths'][0]);
    Storage::assertExists($r['stored_paths'][1]);

    $md = collect($r['stored_paths'])->first(fn ($p) => str_ends_with($p, 'final-report.md'));
    expect($md)->not->toBeNull();
    expect(Storage::get($md))->toContain('# Final');
});

<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('debug webhook error', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);
    $testProjectId = $projekt->id;

    $payload = [
        'meta' => [
            'projekt_id' => $testProjectId,
            'workspace_id' => 'workspace-123',
            'phase' => 'screening',
        ],
        'result' => [
            'type' => 'final_report',
            'summary' => '# Screening Results',
            'data' => [
                'md_files' => [
                    [
                        'path' => 'screening-bericht.md',
                        'content' => '# Screening-Ergebnis\n\nTopics: 42 papers screened.',
                    ],
                ],
            ],
        ],
    ];

    $response = test()->postJson('/api/webhooks/langdock/agent-result', $payload, [
        'X-Langdock-Signature' => 'sha256='.hash_hmac('sha256', json_encode($payload), config('services.langdock.webhook_secret')),
        'X-Langdock-Timestamp' => now()->unix(),
    ]);

    dd($response->status(), $response->json());
});

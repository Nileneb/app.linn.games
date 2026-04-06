<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->owner = User::factory()->withoutTwoFactor()->create();
    $this->nonOwner = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->owner->id]);
    Storage::fake('local');
});

test('webhook to viewer end to end', function () {
    $webhookPayload = [
        'meta' => [
            'projekt_id' => $this->projekt->id,
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
                        'content' => '# Screening-Ergebnis\n\n42 papers screened.',
                    ],
                    [
                        'path' => 'einschluss-liste.md',
                        'content' => '# Included Papers\n\n- Paper 1\n- Paper 2',
                    ],
                ],
            ],
        ],
    ];

    $webhookResponse = $this->postJson('/api/webhooks/langdock/agent-result', $webhookPayload, [
        'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($webhookPayload), config('services.langdock.webhook_secret')),
        'X-Langdock-Timestamp' => now()->unix(),
    ]);

    $webhookResponse->assertStatus(200);

    Storage::disk('local')->assertExists("recherche/{$this->projekt->id}/screening/screening-bericht.md");
    Storage::disk('local')->assertExists("recherche/{$this->projekt->id}/screening/einschluss-liste.md");

    $viewerResponse = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $viewerResponse->assertStatus(200);
});

test('webhook then viewer unauthorized', function () {
    $webhookPayload = [
        'meta' => [
            'projekt_id' => $this->projekt->id,
            'workspace_id' => 'workspace-123',
            'phase' => 'screening',
        ],
        'result' => [
            'type' => 'final_report',
            'summary' => '# Results',
            'data' => [
                'md_files' => [
                    [
                        'path' => 'report.md',
                        'content' => '# Report Content',
                    ],
                ],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/langdock/agent-result', $webhookPayload, [
        'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($webhookPayload), config('services.langdock.webhook_secret')),
        'X-Langdock-Timestamp' => now()->unix(),
    ])->assertStatus(200);

    $this->actingAs($this->nonOwner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening")
        ->assertStatus(403);
});

test('webhook then viewer invalid phase', function () {
    $webhookPayload = [
        'meta' => [
            'projekt_id' => $this->projekt->id,
            'workspace_id' => 'workspace-123',
            'phase' => 'screening',
        ],
        'result' => [
            'type' => 'final_report',
            'summary' => '# Results',
            'data' => [
                'md_files' => [
                    [
                        'path' => 'report.md',
                        'content' => '# Screening Report',
                    ],
                ],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/langdock/agent-result', $webhookPayload, [
        'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($webhookPayload), config('services.langdock.webhook_secret')),
        'X-Langdock-Timestamp' => now()->unix(),
    ])->assertStatus(200);

    $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/auswertung")
        ->assertStatus(404);
});

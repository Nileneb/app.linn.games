<?php

use App\Models\Recherche\Projekt;
use App\Models\PhaseAgentResult;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->owner->id]);
});

test('webhook handler persists markdown files', function () {
    $payload = [
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
                    ['path' => 'screening-bericht.md', 'content' => '# Screening-Ergebnis\n\nTopics: 42 papers screened.'],
                    ['path' => 'einschluss-liste.md', 'content' => '# Included Papers\n\n- Paper 1\n- Paper 2'],
                ],
            ],
        ],
    ];

    $timestamp = now()->unix();
    $signedPayload = $timestamp . '.' . json_encode($payload);
    $signature = 'sha256=' . hash_hmac('sha256', $signedPayload, config('services.langdock.webhook_secret'));

    $response = $this->postJson('/api/webhooks/langdock/agent-result', $payload, [
        'X-Langdock-Signature' => $signature,
        'X-Langdock-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(200);

    Storage::disk('local')->assertExists("recherche/{$this->projekt->id}/screening/screening-bericht.md");
    Storage::disk('local')->assertExists("recherche/{$this->projekt->id}/screening/einschluss-liste.md");

    $this->assertDatabaseHas('phase_agent_results', [
        'projekt_id' => $this->projekt->id,
        'phase' => 'screening',
        'status' => 'completed',
    ]);
});

test('webhook handler rejects invalid signature', function () {
    $payload = [
        'meta' => ['projekt_id' => $this->projekt->id, 'phase' => 'screening'],
        'result' => ['type' => 'final_report', 'data' => ['md_files' => []]],
    ];

    $response = $this->postJson('/api/webhooks/langdock/agent-result', $payload, [
        'X-Langdock-Signature' => 'sha256=invalid',
        'X-Langdock-Timestamp' => now()->unix(),
    ]);

    $response->assertStatus(401);
});

test('webhook handler validates required fields', function () {
    $invalidPayload = ['meta' => [], 'result' => []];

    $timestamp = now()->unix();
    $signedPayload = $timestamp . '.' . json_encode($invalidPayload);
    $signature = 'sha256=' . hash_hmac('sha256', $signedPayload, config('services.langdock.webhook_secret'));

    $response = $this->postJson('/api/webhooks/langdock/agent-result', $invalidPayload, [
        'X-Langdock-Signature' => $signature,
        'X-Langdock-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['meta.projekt_id', 'meta.phase', 'result.data.md_files']);
});

test('webhook handler rejects path traversal attempts', function () {
    $payload = [
        'meta' => [
            'projekt_id' => $this->projekt->id,
            'phase' => 'screening',
        ],
        'result' => [
            'type' => 'final_report',
            'data' => [
                'md_files' => [
                    ['path' => '../../../dangerous.md', 'content' => 'malicious content'],
                ],
            ],
        ],
    ];

    $timestamp = now()->unix();
    $signedPayload = $timestamp . '.' . json_encode($payload);
    $signature = 'sha256=' . hash_hmac('sha256', $signedPayload, config('services.langdock.webhook_secret'));

    $response = $this->postJson('/api/webhooks/langdock/agent-result', $payload, [
        'X-Langdock-Signature' => $signature,
        'X-Langdock-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(400);
});

test('webhook handler rejects absolute paths', function () {
    $payload = [
        'meta' => [
            'projekt_id' => $this->projekt->id,
            'phase' => 'screening',
        ],
        'result' => [
            'type' => 'final_report',
            'data' => [
                'md_files' => [
                    ['path' => '/etc/passwd.md', 'content' => 'malicious content'],
                ],
            ],
        ],
    ];

    $timestamp = now()->unix();
    $signedPayload = $timestamp . '.' . json_encode($payload);
    $signature = 'sha256=' . hash_hmac('sha256', $signedPayload, config('services.langdock.webhook_secret'));

    $response = $this->postJson('/api/webhooks/langdock/agent-result', $payload, [
        'X-Langdock-Signature' => $signature,
        'X-Langdock-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(400);
});

<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Recherche\Projekt;
use App\Models\PhaseAgentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentResultWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $testProjectId;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withoutTwoFactor()->create();
        $projekt = Projekt::factory()->create(['user_id' => $this->owner->id]);
        $this->testProjectId = $projekt->id;

        Storage::fake('local');
    }

    public function test_webhook_handler_persists_markdown_files(): void
    {
        $payload = [
            'meta' => [
                'projekt_id' => $this->testProjectId,
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
                        [
                            'path' => 'einschluss-liste.md',
                            'content' => '# Included Papers\n\n- Paper 1\n- Paper 2',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/langdock/agent-result', $payload, [
            'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), config('services.langdock.webhook_secret')),
            'X-Langdock-Timestamp' => now()->unix(),
        ]);

        $response->assertStatus(200);

        // Verify files written to storage
        Storage::disk('local')->assertExists("recherche/{$this->testProjectId}/screening/screening-bericht.md");
        Storage::disk('local')->assertExists("recherche/{$this->testProjectId}/screening/einschluss-liste.md");

        // Verify PhaseAgentResult record created
        $this->assertDatabaseHas('phase_agent_results', [
            'projekt_id' => $this->testProjectId,
            'phase' => 'screening',
            'status' => 'completed',
        ]);
    }

    public function test_webhook_handler_rejects_invalid_signature(): void
    {
        $payload = [
            'meta' => ['projekt_id' => $this->testProjectId, 'phase' => 'screening'],
            'result' => ['type' => 'final_report', 'data' => ['md_files' => []]],
        ];

        $response = $this->postJson('/api/webhooks/langdock/agent-result', $payload, [
            'X-Langdock-Signature' => 'sha256=invalid',
            'X-Langdock-Timestamp' => now()->unix(),
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_handler_validates_required_fields(): void
    {
        $invalidPayload = ['meta' => [], 'result' => []];

        $response = $this->postJson('/api/webhooks/langdock/agent-result', $invalidPayload, [
            'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($invalidPayload), config('services.langdock.webhook_secret')),
            'X-Langdock-Timestamp' => now()->unix(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['meta.projekt_id', 'meta.phase', 'result.data.md_files']);
    }
}

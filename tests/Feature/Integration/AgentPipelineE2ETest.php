<?php

namespace Tests\Feature\Integration;

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentPipelineE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Projekt $projekt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withoutTwoFactor()->create();
        $this->projekt = Projekt::factory()->create(['user_id' => $this->owner->id]);

        Storage::fake('local');
    }

    public function test_webhook_to_viewer_end_to_end(): void
    {
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
                        ['path' => 'screening-bericht.md', 'content' => '# Screening-Ergebnis\n\n42 papers screened.'],
                        ['path' => 'einschluss-liste.md', 'content' => '# Included Papers\n\n- Paper 1\n- Paper 2'],
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
    }

    public function test_webhook_then_viewer_unauthorized(): void
    {
        $other = User::factory()->withoutTwoFactor()->create();

        $webhookPayload = [
            'meta' => ['projekt_id' => $this->projekt->id, 'workspace_id' => 'ws-123', 'phase' => 'screening'],
            'result' => ['type' => 'final_report', 'summary' => '# Results', 'data' => ['md_files' => [['path' => 'report.md', 'content' => '# Report']]]],
        ];

        $this->postJson('/api/webhooks/langdock/agent-result', $webhookPayload, [
            'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($webhookPayload), config('services.langdock.webhook_secret')),
            'X-Langdock-Timestamp' => now()->unix(),
        ])->assertStatus(200);

        $this->actingAs($other)->get("/recherche/{$this->projekt->id}/ergebnisse/screening")->assertStatus(403);
    }

    public function test_webhook_then_viewer_invalid_phase(): void
    {
        $webhookPayload = [
            'meta' => ['projekt_id' => $this->projekt->id, 'workspace_id' => 'ws-123', 'phase' => 'screening'],
            'result' => ['type' => 'final_report', 'summary' => '# Results', 'data' => ['md_files' => [['path' => 'report.md', 'content' => '# Report']]]],
        ];

        $this->postJson('/api/webhooks/langdock/agent-result', $webhookPayload, [
            'X-Langdock-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($webhookPayload), config('services.langdock.webhook_secret')),
            'X-Langdock-Timestamp' => now()->unix(),
        ])->assertStatus(200);

        $this->actingAs($this->owner)->get("/recherche/{$this->projekt->id}/ergebnisse/auswertung")->assertStatus(404);
    }
}

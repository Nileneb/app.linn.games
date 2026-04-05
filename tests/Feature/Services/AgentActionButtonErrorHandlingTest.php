<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LangdockAgentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function makeTestProject(): Projekt
{
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Test']);
    return Projekt::factory()->create([
        'user_id'        => $user->id,
        'workspace_id'   => $workspace->id,
        'forschungsfrage' => 'Test research question',
    ]);
}

// ─── LangdockAgentService 404 Handling ──────────────────────

test('LangdockAgentService handles 404 with descriptive error message', function () {
    Http::fake([
        'api.langdock.com/*' => Http::response(
            ['error' => 'Agent not found'],
            404,
        ),
    ]);

    config([
        'services.langdock.api_key' => 'test-key',
        'services.langdock.base_url' => 'https://api.langdock.com/agent/v1/chat/completions',
    ]);

    $service = app(LangdockAgentService::class);

    try {
        $service->call('unknown-agent-id', [['role' => 'user', 'content' => 'test']]);
        $this->fail('Expected LangdockAgentException was not thrown.');
    } catch (\App\Services\LangdockAgentException $exception) {
        expect($exception->getMessage())->toContain('unknown-agent-id');
        expect($exception->getMessage())->toContain('nicht gefunden');
    }
});

test('LangdockAgentService handles 500 with generic error message', function () {
    Http::fake([
        'api.langdock.com/*' => Http::response(
            ['error' => 'Server error'],
            500,
        ),
    ]);

    config([
        'services.langdock.api_key' => 'test-key',
        'services.langdock.base_url' => 'https://api.langdock.com/agent/v1/chat/completions',
        'services.langdock.retry_attempts' => 1,
    ]);

    $service = app(LangdockAgentService::class);

    expect(fn () => $service->call('test-agent', [['role' => 'user', 'content' => 'test']]))
        ->toThrow(\App\Services\LangdockAgentException::class);
});

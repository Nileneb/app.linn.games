<?php

use App\Models\User;
use App\Models\Workspace;
use App\Services\ClaudeAgentException;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.api_key' => 'test-key-dummy']);

    $this->claudeStatus = 200;

    Http::fake(function () {
        if ($this->claudeStatus === 401) {
            return Http::response(['error' => ['type' => 'authentication_error']], 401);
        }

        return Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => '{"meta":{"version":1},"result":{"type":"test","summary":"OK","data":{}},"db":{"bootstrapped":false,"loaded":[]},"next":{"route_to":null,"reason":null},"warnings":[]}']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 200],
        ], 200);
    });

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Test Workspace',
    ]);
    \App\Models\WorkspaceUser::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
    app(\App\Services\CreditService::class)->topUp($workspace, 10000);
    $this->workspace = $workspace->fresh();
    $this->userId = $user->id;
});

test('callByConfigKey gibt content und tokens zurück', function () {
    $service = app(ClaudeService::class);

    $result = $service->callByConfigKey('chat-agent', [
        ['role' => 'user', 'content' => 'Hallo'],
    ], [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->userId,
    ]);

    expect($result)
        ->toHaveKey('content')
        ->toHaveKey('raw')
        ->toHaveKey('tokens_used');

    expect($result['tokens_used'])->toBe(300); // 100 input + 200 output
});

test('callByConfigKey wirft exception bei unbekanntem config-key', function () {
    expect(fn () => app(ClaudeService::class)->callByConfigKey('unbekannt', [
        ['role' => 'user', 'content' => 'test'],
    ]))->toThrow(ClaudeAgentException::class);
});

test('callByConfigKey sendet korrekte anthropic-request-struktur', function () {
    app(ClaudeService::class)->callByConfigKey('chat-agent', [
        ['role' => 'user', 'content' => 'Test'],
    ], ['workspace_id' => $this->workspace->id, 'user_id' => $this->userId]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), 'api.anthropic.com')
            && isset($body['model'])
            && isset($body['messages'])
            && isset($body['max_tokens'])
            && $body['messages'][0]['role'] === 'user'
            && $body['messages'][0]['content'] === 'Test';
    });
});

test('callByConfigKey zieht credits ab', function () {
    $balanceBefore = $this->workspace->credits_balance_cents;

    app(ClaudeService::class)->callByConfigKey('chat-agent', [
        ['role' => 'user', 'content' => 'Test'],
    ], ['workspace_id' => $this->workspace->id, 'user_id' => $this->userId]);

    expect($this->workspace->fresh()->credits_balance_cents)->toBeLessThan($balanceBefore);
});

test('callByConfigKey wirft exception bei 401', function () {
    $this->claudeStatus = 401;

    expect(fn () => app(ClaudeService::class)->callByConfigKey('chat-agent', [
        ['role' => 'user', 'content' => 'test'],
    ]))->toThrow(ClaudeAgentException::class);
});

test('callByConfigKey wirft exception bei 401 und zieht keine credits ab', function () {
    $this->claudeStatus = 401;
    $balanceBefore = $this->workspace->credits_balance_cents;

    expect(fn () => app(ClaudeService::class)->callByConfigKey('chat-agent', [
        ['role' => 'user', 'content' => 'test'],
    ], [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->userId,
    ]))->toThrow(ClaudeAgentException::class);

    expect($this->workspace->fresh()->credits_balance_cents)->toBe($balanceBefore);
});

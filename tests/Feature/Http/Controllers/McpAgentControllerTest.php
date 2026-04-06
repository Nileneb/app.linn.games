<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.base_url', 'https://api.langdock.com/agent/v1/chat/completions');
    Config::set('services.mcp.auth_token', 'mcp-secret');
});

test('mcp agent endpoint forwards request to langdock and returns agent content', function () {
    Http::fake([
        'https://api.langdock.com/agent/v1/chat/completions' => Http::response([
            'messages' => [[
                'content' => 'Langdock antwortet',
            ]],
            'usage' => ['total_tokens' => 10],
        ], 200),
    ]);

    $payload = [
        'agent_id' => 'agent-uuid',
        'messages' => [
            ['role' => 'user', 'content' => 'Hallo Agent'],
        ],
    ];

    $response = $this->postJson('/api/mcp/agent-call', $payload, [
        'Authorization' => 'Bearer mcp-secret',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'content' => 'Langdock antwortet',
        ]);
});

test('mcp agent endpoint rejects missing bearer token', function () {
    Config::set('services.mcp.auth_token', 'mcp-secret');

    $response = $this->postJson('/api/mcp/agent-call', [
        'agent_id' => 'agent-uuid',
        'messages' => [
            ['role' => 'user', 'content' => 'Hallo Agent'],
        ],
    ]);

    $response->assertStatus(401);
});

test('mcp agent endpoint validates required fields', function () {
    $response = $this->postJson('/api/mcp/agent-call', [
        'agent_id' => '',
        'messages' => [],
    ], [
        'Authorization' => 'Bearer mcp-secret',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id', 'messages']);
});

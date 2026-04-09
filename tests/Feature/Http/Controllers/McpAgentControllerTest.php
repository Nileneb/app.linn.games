<?php

use App\Services\ClaudeAgentException;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('services.anthropic.agents.test_agent', 'agents/test-agent.md');
    Config::set('services.mcp.auth_token', 'mcp-secret');
});

test('mcp agent endpoint forwards request to claude and returns agent content', function () {
    $mock = Mockery::mock(ClaudeService::class);
    $mock->shouldReceive('callByConfigKey')
        ->once()
        ->andReturn([
            'content' => 'Claude antwortet',
            'raw' => [],
            'tokens_used' => 10,
        ]);
    app()->instance(ClaudeService::class, $mock);

    $payload = [
        'agent_id' => 'test_agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Hallo Agent'],
        ],
    ];

    $response = $this->postJson('/api/mcp/agent-call', $payload, [
        'Authorization' => 'Bearer mcp-secret',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('content', 'Claude antwortet');
});

test('mcp agent endpoint returns 500 on claude agent exception', function () {
    $mock = Mockery::mock(ClaudeService::class);
    $mock->shouldReceive('callByConfigKey')
        ->once()
        ->andThrow(new ClaudeAgentException('Agent nicht konfiguriert', 500));
    app()->instance(ClaudeService::class, $mock);

    $payload = [
        'agent_id' => 'unknown_agent',
        'messages' => [
            ['role' => 'user', 'content' => 'test'],
        ],
    ];

    $response = $this->postJson('/api/mcp/agent-call', $payload, [
        'Authorization' => 'Bearer mcp-secret',
    ]);

    $response->assertStatus(500)
        ->assertJsonPath('success', false);
});

test('mcp agent endpoint rejects missing bearer token', function () {
    $response = $this->postJson('/api/mcp/agent-call', [
        'agent_id' => 'test_agent',
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

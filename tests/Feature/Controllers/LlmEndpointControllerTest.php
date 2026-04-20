<?php

use App\Models\LlmEndpoint;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('services.mcp.service_token', 'test-service-token');
});

test('returns platform fallback when no endpoint configured', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $response = $this->getJson("/api/mcp-service/llm-endpoint/{$workspace->id}", [
        'Authorization' => 'Bearer test-service-token',
    ]);

    $response->assertOk()->assertJson(['provider' => 'platform']);
});

test('returns 404 for unknown workspace', function () {
    $response = $this->getJson('/api/mcp-service/llm-endpoint/ffffffff-ffff-ffff-ffff-ffffffffffff', [
        'Authorization' => 'Bearer test-service-token',
    ]);

    $response->assertStatus(404);
});

test('returns 401 without service token', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $response = $this->getJson("/api/mcp-service/llm-endpoint/{$workspace->id}");

    $response->assertStatus(401);
});

test('returns decrypted api_key for configured endpoint', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $endpoint = new LlmEndpoint([
        'workspace_id' => $workspace->id,
        'provider' => 'anthropic',
        'base_url' => 'https://api.anthropic.com',
        'model' => 'claude-sonnet-4-6',
        'is_default' => true,
    ]);
    $endpoint->api_key = 'sk-ant-real-key';
    $endpoint->save();

    $response = $this->getJson("/api/mcp-service/llm-endpoint/{$workspace->id}", [
        'Authorization' => 'Bearer test-service-token',
    ]);

    $response->assertOk()
        ->assertJsonFragment([
            'provider' => 'anthropic',
            'base_url' => 'https://api.anthropic.com',
            'model' => 'claude-sonnet-4-6',
            'api_key' => 'sk-ant-real-key',
        ]);
});

test('agent query param returns scoped endpoint', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'ollama',
        'base_url' => 'http://localhost:11434',
        'model' => 'llama3',
        'is_default' => true,
    ]);
    LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'anthropic',
        'base_url' => 'https://api.anthropic.com',
        'model' => 'claude-opus-4-7',
        'agent_scope' => 'chat-agent',
    ]);

    $chatResponse = $this->getJson("/api/mcp-service/llm-endpoint/{$workspace->id}?agent=chat-agent", [
        'Authorization' => 'Bearer test-service-token',
    ]);
    $chatResponse->assertOk()->assertJsonFragment(['provider' => 'anthropic', 'model' => 'claude-opus-4-7']);

    $otherResponse = $this->getJson("/api/mcp-service/llm-endpoint/{$workspace->id}?agent=scoping_mapping_agent", [
        'Authorization' => 'Bearer test-service-token',
    ]);
    $otherResponse->assertOk()->assertJsonFragment(['provider' => 'ollama', 'model' => 'llama3']);
});

test('platform-provider endpoint still returns platform fallback', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    // User explicitly sets a "platform" entry — Controller behandelt das wie kein Endpoint
    LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'platform',
        'base_url' => 'https://api.anthropic.com',
        'model' => 'claude-sonnet-4-6',
        'is_default' => true,
    ]);

    $response = $this->getJson("/api/mcp-service/llm-endpoint/{$workspace->id}", [
        'Authorization' => 'Bearer test-service-token',
    ]);

    $response->assertOk()->assertJson(['provider' => 'platform']);
});

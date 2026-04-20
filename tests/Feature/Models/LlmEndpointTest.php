<?php

use App\Models\LlmEndpoint;
use App\Models\User;
use App\Models\Workspace;

test('api_key mutator encrypts at write, accessor decrypts at read', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $endpoint = new LlmEndpoint([
        'workspace_id' => $workspace->id,
        'provider' => 'anthropic',
        'base_url' => 'https://api.anthropic.com',
        'model' => 'claude-opus-4-7',
        'is_default' => true,
    ]);
    $endpoint->api_key = 'sk-ant-plaintext-secret';
    $endpoint->save();

    // Encrypted in DB
    $raw = \DB::table('llm_endpoints')->where('id', $endpoint->id)->first();
    expect($raw->api_key_encrypted)->not->toBe('sk-ant-plaintext-secret');
    expect($raw->api_key_encrypted)->not->toBeNull();

    // Decryptable via accessor
    expect($endpoint->fresh()->api_key)->toBe('sk-ant-plaintext-secret');
});

test('resolveFor returns agent-specific over default', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $default = LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'ollama',
        'base_url' => 'http://localhost:11434',
        'model' => 'llama3',
        'is_default' => true,
    ]);
    $specific = LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'anthropic',
        'base_url' => 'https://api.anthropic.com',
        'model' => 'claude-sonnet-4-6',
        'agent_scope' => 'chat-agent',
        'is_default' => false,
    ]);

    expect(LlmEndpoint::resolveFor($workspace, 'chat-agent')->id)->toBe($specific->id);
    expect(LlmEndpoint::resolveFor($workspace, 'scoping_mapping_agent')->id)->toBe($default->id);
});

test('resolveFor returns null when no endpoint configured', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    expect(LlmEndpoint::resolveFor($workspace, 'chat-agent'))->toBeNull();
});

test('resolveFor returns default when agent has no specific endpoint', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $default = LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'ollama',
        'base_url' => 'http://localhost:11434',
        'model' => 'llama3',
        'is_default' => true,
    ]);

    expect(LlmEndpoint::resolveFor($workspace, 'unknown_agent')->id)->toBe($default->id);
});

test('api_key nullable for local ollama without auth', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $endpoint = LlmEndpoint::create([
        'workspace_id' => $workspace->id,
        'provider' => 'ollama',
        'base_url' => 'http://localhost:11434',
        'model' => 'llama3',
        'is_default' => true,
    ]);

    expect($endpoint->api_key)->toBeNull();
});

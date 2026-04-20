<?php

use App\Models\User;

test('defaults to platform when nothing set', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    expect($user->llmProviderConfig())->toBe(['type' => 'platform']);
});

test('returns anthropic-byo config when type+key set', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'anthropic-byo',
        'llm_api_key' => 'sk-ant-user-key',
        'llm_custom_model' => 'claude-opus-4-7',
    ]);

    $config = $user->llmProviderConfig();

    expect($config['type'])->toBe('anthropic-byo');
    expect($config['api_key'])->toBe('sk-ant-user-key');
    expect($config['model'])->toBe('claude-opus-4-7');
});

test('falls back to platform when anthropic-byo has no key', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'anthropic-byo',
        'llm_api_key' => null,
    ]);

    expect($user->llmProviderConfig())->toBe(['type' => 'platform']);
});

test('returns openai-compatible config when type+endpoint+model set', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'openai-compatible',
        'llm_endpoint' => 'http://localhost:11434/',
        'llm_api_key' => 'optional-key',
        'llm_custom_model' => 'qwen2.5:7b',
    ]);

    $config = $user->llmProviderConfig();

    expect($config['type'])->toBe('openai-compatible');
    expect($config['endpoint'])->toBe('http://localhost:11434'); // trailing slash stripped
    expect($config['api_key'])->toBe('optional-key');
    expect($config['model'])->toBe('qwen2.5:7b');
});

test('openai-compatible works without api_key (local Ollama)', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'openai-compatible',
        'llm_endpoint' => 'http://localhost:11434',
        'llm_api_key' => null,
        'llm_custom_model' => 'llama3.2',
    ]);

    $config = $user->llmProviderConfig();

    expect($config['type'])->toBe('openai-compatible');
    expect($config['api_key'])->toBeNull();
});

test('falls back to platform when openai-compatible missing endpoint', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'openai-compatible',
        'llm_endpoint' => null,
        'llm_custom_model' => 'qwen',
    ]);

    expect($user->llmProviderConfig())->toBe(['type' => 'platform']);
});

test('llm_api_key is encrypted at rest', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_api_key' => 'sk-plain-secret',
    ]);

    $row = \DB::table('users')->where('id', $user->id)->first();

    expect($row->llm_api_key)->not->toBe('sk-plain-secret');
    expect($user->fresh()->llm_api_key)->toBe('sk-plain-secret');
});

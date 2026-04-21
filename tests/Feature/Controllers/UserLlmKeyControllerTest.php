<?php

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\Support\TestJwtKeys;

beforeEach(function () {
    Cache::flush();
    Config::set('services.mcp.service_token', 'test-service-secret');
    Config::set('services.jwt.private_key', TestJwtKeys::privateKey());
    Config::set('services.jwt.public_key', TestJwtKeys::publicKey());
    Config::set('services.jwt.issuer', 'https://app.linn.games');
    Config::set('services.jwt.audience', 'mayringcoder');
});

function signUserJwt(string $sub, array $overrides = []): string
{
    $now = time();
    $payload = array_merge([
        'iss' => 'https://app.linn.games',
        'aud' => 'mayringcoder',
        'sub' => $sub,
        'iat' => $now,
        'exp' => $now + 600,
        'jti' => Str::uuid()->toString(),
    ], $overrides);

    return JWT::encode($payload, TestJwtKeys::privateKey(), 'RS256');
}

test('returns decrypted api_key when service token and user jwt are valid', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'anthropic-byo',
        'llm_api_key' => 'sk-ant-user-secret-123',
    ]);

    $jwt = signUserJwt((string) $user->id);

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt], [
        'Authorization' => 'Bearer test-service-secret',
    ])
        ->assertOk()
        ->assertJson([
            'api_key' => 'sk-ant-user-secret-123',
            'provider' => 'anthropic-byo',
        ]);
});

test('rejects expired user jwt with 401', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'anthropic-byo',
        'llm_api_key' => 'sk-ant-user-secret',
    ]);

    $expiredJwt = signUserJwt((string) $user->id, [
        'iat' => time() - 3600,
        'exp' => time() - 60,
    ]);

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $expiredJwt], [
        'Authorization' => 'Bearer test-service-secret',
    ])->assertStatus(401);
});

test('returns 404 when sub references non-existent user', function () {
    $jwt = signUserJwt('999999');

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt], [
        'Authorization' => 'Bearer test-service-secret',
    ])->assertStatus(404);
});

test('returns 404 when user has no api_key configured', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'platform',
        'llm_api_key' => null,
    ]);

    $jwt = signUserJwt((string) $user->id);

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt], [
        'Authorization' => 'Bearer test-service-secret',
    ])->assertStatus(404);
});

test('returns openai-compatible api_key with matching provider field', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'openai-compatible',
        'llm_endpoint' => 'http://three.linn.games:11434',
        'llm_custom_model' => 'llama3.2',
        'llm_api_key' => 'sk-openrouter-user-key',
    ]);

    $jwt = signUserJwt((string) $user->id);

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt], [
        'Authorization' => 'Bearer test-service-secret',
    ])
        ->assertOk()
        ->assertJson([
            'api_key' => 'sk-openrouter-user-key',
            'provider' => 'openai-compatible',
        ]);
});

test('rejects request without service token', function () {
    $jwt = signUserJwt('1');

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt])
        ->assertStatus(401);
});

test('rejects request with invalid service token', function () {
    $jwt = signUserJwt('1');

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt], [
        'Authorization' => 'Bearer wrong-token',
    ])->assertStatus(401);
});

test('rejects user jwt in outer Authorization header', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_api_key' => 'sk-ant-secret',
    ]);

    $jwt = signUserJwt((string) $user->id);

    $this->postJson('/api/mcp/user-llm-key', ['jwt' => $jwt], [
        'Authorization' => 'Bearer '.$jwt,
    ])->assertStatus(401);
});

test('returns 422 when jwt body field is missing', function () {
    $this->postJson('/api/mcp/user-llm-key', [], [
        'Authorization' => 'Bearer test-service-secret',
    ])->assertStatus(422);
});

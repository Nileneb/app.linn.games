<?php

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\TestJwtKeys;

beforeEach(function () {
    Config::set('services.mcp.service_token', 'test-service-secret');
    Config::set('services.jwt.private_key', TestJwtKeys::privateKey());
    Config::set('services.jwt.public_key', TestJwtKeys::publicKey());
    Config::set('services.jwt.issuer', 'https://app.linn.games');
    Config::set('services.jwt.audience', 'mayringcoder');

    Route::middleware(\App\Http\Middleware\VerifyMcpToken::class)
        ->any('/_test/mcp-guarded', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'auth_mode' => $request->attributes->get('auth_mode'),
                'jwt_subject' => $request->attributes->get('jwt_subject'),
                'jwt_workspace_id' => $request->attributes->get('jwt_workspace_id'),
                'jwt_scope' => $request->attributes->get('jwt_scope'),
            ]);
        });
});

function signTestJwt(array $overrides = []): string
{
    $now = time();
    $payload = array_merge([
        'iss' => 'https://app.linn.games',
        'aud' => 'mayringcoder',
        'sub' => 'user-42',
        'iat' => $now,
        'exp' => $now + 600,
        'jti' => Str::uuid()->toString(),
        'workspace_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
        'scope' => ['mcp:memory'],
    ], $overrides);

    return JWT::encode($payload, TestJwtKeys::privateKey(), 'RS256');
}

test('rejects requests without Bearer header', function () {
    $this->get('/_test/mcp-guarded')->assertStatus(401);
});

test('accepts valid service token (BC path)', function () {
    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer test-service-secret'])
        ->assertOk()
        ->assertJsonPath('auth_mode', 'service');
});

test('accepts valid RS256 JWT and exposes claims as request attributes', function () {
    $token = signTestJwt(['sub' => 'user-99', 'workspace_id' => 'aaaa-bbbb']);

    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer '.$token])
        ->assertOk()
        ->assertJsonPath('auth_mode', 'jwt')
        ->assertJsonPath('jwt_subject', 'user-99')
        ->assertJsonPath('jwt_workspace_id', 'aaaa-bbbb')
        ->assertJsonPath('jwt_scope.0', 'mcp:memory');
});

test('rejects JWT with wrong issuer', function () {
    $token = signTestJwt(['iss' => 'https://evil.example']);
    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(401);
});

test('rejects JWT with wrong audience', function () {
    $token = signTestJwt(['aud' => 'someone-else']);
    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(401);
});

test('rejects expired JWT', function () {
    $token = signTestJwt(['exp' => time() - 10, 'iat' => time() - 3600]);
    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(401);
});

test('rejects garbage token', function () {
    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer not.a.jwt'])
        ->assertStatus(401);
});

test('rejects JWT signed with a different private key', function () {
    $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($otherKey, $otherPem);
    $token = JWT::encode([
        'iss' => 'https://app.linn.games',
        'aud' => 'mayringcoder',
        'sub' => 'user-99',
        'exp' => time() + 600,
    ], $otherPem, 'RS256');

    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(401);
});

test('401 when public key is missing and no service token match', function () {
    Config::set('services.jwt.public_key', '');
    $this->get('/_test/mcp-guarded', ['Authorization' => 'Bearer anything-that-is-not-service-token'])
        ->assertStatus(401);
});

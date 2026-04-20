<?php

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Tests\Support\TestJwtKeys;

beforeEach(function () {
    Config::set('services.jwt.private_key', TestJwtKeys::privateKey());
    Config::set('services.jwt.public_key', TestJwtKeys::publicKey());
    Config::set('services.jwt.issuer', 'https://app.linn.games');
    Config::set('services.jwt.audience', 'mayringcoder');
    Config::set('services.jwt.ttl', 3600);
    Config::set('services.jwt.refresh_grace_seconds', 7 * 24 * 3600);

    // Rate-Limit-Cache flushen damit Tests nicht auf 429 laufen (array-cache persistiert im Prozess)
    \Illuminate\Support\Facades\Cache::flush();
});

function signTestJwtForRefresh(string $userId, int $expOffset = 600): string
{
    $now = time();

    return JWT::encode([
        'iss' => 'https://app.linn.games',
        'aud' => 'mayringcoder',
        'sub' => $userId,
        'iat' => $now - 100,
        'exp' => $now + $expOffset,
    ], TestJwtKeys::privateKey(), 'RS256');
}

test('issues fresh JWT when bearer is valid (not expired)', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    $oldToken = signTestJwtForRefresh($user->id, expOffset: 100);

    $response = $this->postJson('/api/mayring/refresh-token', [], [
        'Authorization' => 'Bearer '.$oldToken,
    ]);

    $response->assertOk()->assertJsonStructure(['token']);
    $newToken = $response->json('token');

    $claims = JWT::decode($newToken, new Key(TestJwtKeys::publicKey(), 'RS256'));
    expect($claims->sub)->toBe((string) $user->id);
    expect($claims->exp)->toBeGreaterThan(time() + 3500); // fresh 1h TTL
});

test('accepts expired JWT within grace period', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    // Token expired 1 hour ago, grace is 7 days → should still be accepted
    $oldToken = signTestJwtForRefresh($user->id, expOffset: -3600);

    $response = $this->postJson('/api/mayring/refresh-token', [], [
        'Authorization' => 'Bearer '.$oldToken,
    ]);

    $response->assertOk();
});

test('rejects JWT expired beyond grace period', function () {
    Config::set('services.jwt.refresh_grace_seconds', 300);

    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    // Token expired 1 hour ago, grace is 5 min → reject
    $oldToken = signTestJwtForRefresh($user->id, expOffset: -3600);

    $response = $this->postJson('/api/mayring/refresh-token', [], [
        'Authorization' => 'Bearer '.$oldToken,
    ]);

    $response->assertStatus(401);
});

test('rejects request without bearer', function () {
    $response = $this->postJson('/api/mayring/refresh-token');

    $response->assertStatus(401);
});

test('rejects JWT with wrong audience', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    $badToken = JWT::encode([
        'iss' => 'https://app.linn.games',
        'aud' => 'wrong-audience',
        'sub' => $user->id,
        'iat' => time(),
        'exp' => time() + 600,
    ], TestJwtKeys::privateKey(), 'RS256');

    $response = $this->postJson('/api/mayring/refresh-token', [], [
        'Authorization' => 'Bearer '.$badToken,
    ]);

    $response->assertStatus(401);
});

test('403 when workspace has no mayring subscription', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    // workspace default is mayring_active=false

    $oldToken = signTestJwtForRefresh($user->id);

    $response = $this->postJson('/api/mayring/refresh-token', [], [
        'Authorization' => 'Bearer '.$oldToken,
    ]);

    $response->assertStatus(403);
});

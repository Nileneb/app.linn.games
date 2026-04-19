<?php

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

test('token endpoint gibt access_token zurück bei gültigem PKCE', function () {
    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    $redirectUri = 'https://example.com/callback';
    $code = Str::random(64);

    Redis::setex("paper_search_oauth:{$code}", 300, json_encode([
        'token'                 => '1|test_sanctum_token',
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => 'S256',
        'redirect_uri'          => $redirectUri,
    ]));

    $response = $this->postJson('/paper-search/token', [
        'code'          => $code,
        'code_verifier' => $codeVerifier,
        'redirect_uri'  => $redirectUri,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type'])
        ->assertJsonFragment(['token_type' => 'Bearer']);
});

test('token endpoint gibt 400 bei ungültigem code', function () {
    $response = $this->postJson('/paper-search/token', [
        'code'          => 'nonexistent-code',
        'code_verifier' => 'some-verifier',
        'redirect_uri'  => 'https://example.com/callback',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['error' => 'invalid_grant']);
});

test('token endpoint gibt 400 bei falschem code_verifier', function () {
    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    $redirectUri = 'https://example.com/callback';
    $code = Str::random(64);

    Redis::setex("paper_search_oauth:{$code}", 300, json_encode([
        'token'                 => '1|test_token',
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => 'S256',
        'redirect_uri'          => $redirectUri,
    ]));

    $response = $this->postJson('/paper-search/token', [
        'code'          => $code,
        'code_verifier' => 'wrong-verifier',
        'redirect_uri'  => $redirectUri,
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['error' => 'invalid_grant']);
});

test('token endpoint gibt 400 bei falscher redirect_uri', function () {
    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    $code = Str::random(64);

    Redis::setex("paper_search_oauth:{$code}", 300, json_encode([
        'token'                 => '1|test_token',
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => 'S256',
        'redirect_uri'          => 'https://original.com/callback',
    ]));

    $response = $this->postJson('/paper-search/token', [
        'code'          => $code,
        'code_verifier' => $codeVerifier,
        'redirect_uri'  => 'https://attacker.com/callback',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['error' => 'invalid_grant']);
});

test('token endpoint löscht Redis-Key nach erfolgreicher Einlösung', function () {
    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    $redirectUri = 'https://example.com/callback';
    $code = Str::random(64);

    Redis::setex("paper_search_oauth:{$code}", 300, json_encode([
        'token'                 => '1|test_token',
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => 'S256',
        'redirect_uri'          => $redirectUri,
    ]));

    $this->postJson('/paper-search/token', [
        'code'          => $code,
        'code_verifier' => $codeVerifier,
        'redirect_uri'  => $redirectUri,
    ])->assertOk();

    // Zweiter Versuch mit demselben Code schlägt fehl
    $this->postJson('/paper-search/token', [
        'code'          => $code,
        'code_verifier' => $codeVerifier,
        'redirect_uri'  => $redirectUri,
    ])->assertStatus(400);
});

test('authorize endpoint leitet auf pending-approval weiter für inaktive user', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    $this->actingAs($user);

    $response = $this->get('/paper-search/authorize?redirect_uri=https://example.com/cb&code_challenge=abc123&code_challenge_method=S256&state=xyz');

    $response->assertRedirect(route('pending-approval'));
});

test('authorize endpoint gibt 400 ohne redirect_uri', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'active']);
    $this->actingAs($user);

    $response = $this->get('/paper-search/authorize?code_challenge=abc123');

    $response->assertStatus(400);
});

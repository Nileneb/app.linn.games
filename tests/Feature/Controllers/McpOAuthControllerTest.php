<?php

use App\Enums\UserRole;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::all() as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    Config::set('services.jwt.private_key', file_get_contents(base_path('tests/fixtures/jwt/private-test.pem')));
    Config::set('services.jwt.public_key', file_get_contents(base_path('tests/fixtures/jwt/public-test.pem')));
    Config::set('services.jwt.issuer', 'https://app.linn.games');
    Config::set('services.jwt.audience', 'mayringcoder');
    Config::set('services.mayring_mcp.auth_token', 'mayring-outbound-secret');
    Config::set('services.mayring_mcp.endpoint', 'http://mayring-api:8090');
});

test('authorize endpoint issues JWT and POSTs it as token to MCP register-code', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->currentWorkspace();
    $workspace->update(['mayring_active' => true]);

    Http::fake([
        'http://mayring-mcp:8092/authorize/register-code' => Http::response(['ok' => true], 200),
    ]);

    $redirect = $this->actingAs($user)->get(route('mcp.oauth.authorize', [
        'redirect_uri' => 'https://client.example/cb',
        'state' => 'xyz',
        'code_challenge' => 'abc123',
        'code_challenge_method' => 'S256',
    ]));

    $redirect->assertRedirect();
    expect($redirect->headers->get('location'))->toContain('https://client.example/cb');
    expect($redirect->headers->get('location'))->toContain('code=');
    expect($redirect->headers->get('location'))->toContain('state=xyz');

    Http::assertSent(function ($request) use ($workspace) {
        if ($request->url() !== 'http://mayring-mcp:8092/authorize/register-code') {
            return false;
        }
        $body = $request->data();
        if (($body['workspace_id'] ?? null) !== $workspace->id) {
            return false;
        }
        $token = $body['token'] ?? '';
        if (substr_count($token, '.') !== 2) {
            return false;
        }
        $decoded = JWT::decode($token, new Key(file_get_contents(base_path('tests/fixtures/jwt/public-test.pem')), 'RS256'));

        return $decoded->workspace_id === $workspace->id && $decoded->aud === 'mayringcoder';
    });
});

test('authorize endpoint redirects to subscribe page when workspace lacks mayring access', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->actingAs($user)->get(route('mcp.oauth.authorize', [
        'redirect_uri' => 'https://client.example/cb',
    ]));

    $response->assertRedirect(route('mayring.subscribe'));
});

test('authorize endpoint 400s without redirect_uri', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    $this->actingAs($user)->get(route('mcp.oauth.authorize'))
        ->assertStatus(400);
});

test('JWT includes admin scope when user has admin role', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::ADMIN]);
    $user->currentWorkspace()->update(['mayring_active' => true]);

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $this->actingAs($user)->get(route('mcp.oauth.authorize', [
        'redirect_uri' => 'https://client.example/cb',
    ]))->assertRedirect();

    Http::assertSent(function ($request) {
        $body = $request->data();
        if (empty($body['token'])) {
            return false;
        }
        $decoded = JWT::decode($body['token'], new Key(file_get_contents(base_path('tests/fixtures/jwt/public-test.pem')), 'RS256'));
        return in_array('admin', (array) $decoded->scope, true);
    });
});

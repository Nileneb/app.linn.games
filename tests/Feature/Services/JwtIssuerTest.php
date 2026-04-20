<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\JwtIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\Support\TestJwtKeys;

beforeEach(function () {
    foreach (UserRole::all() as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    Config::set('services.jwt.private_key', TestJwtKeys::privateKey());
    Config::set('services.jwt.public_key', TestJwtKeys::publicKey());
    Config::set('services.jwt.issuer', 'https://app.linn.games');
    Config::set('services.jwt.audience', 'mayringcoder');
    Config::set('services.jwt.ttl', 3600);
});

function decodeJwtWithTestKey(string $token): array
{
    $decoded = JWT::decode($token, new Key(TestJwtKeys::publicKey(), 'RS256'));

    return (array) json_decode(json_encode($decoded), true);
}

test('issues RS256 JWT with expected claims for regular user', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);

    $parts = explode('.', $token);
    expect($parts)->toHaveCount(3);

    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    expect($header['alg'])->toBe('RS256');

    $claims = decodeJwtWithTestKey($token);
    expect($claims['iss'])->toBe('https://app.linn.games');
    expect($claims['aud'])->toBe('mayringcoder');
    expect($claims['sub'])->toBe((string) $user->id);
    expect($claims['workspace_id'])->toBe($workspace->id);
    expect($claims['email'])->toBe($user->email);
    expect($claims['scope'])->toBe(['mcp:memory']);
    expect($claims['exp'])->toBeGreaterThan(time());
    expect($claims['exp'])->toBeLessThanOrEqual(time() + 3600);
    expect($claims['jti'])->toBeString()->not->toBeEmpty();
});

test('includes admin scope for users with admin role', function () {
    $admin = User::factory()->withoutTwoFactor()->create();
    $admin->syncRoles([UserRole::ADMIN]);
    $workspace = Workspace::factory()->create(['owner_id' => $admin->id]);

    $token = app(JwtIssuer::class)->issueForUser($admin, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['scope'])->toContain('mcp:memory');
    expect($claims['scope'])->toContain('admin');
});

test('excludes admin scope for non-admin users', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::EDITOR]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['scope'])->not->toContain('admin');
});

test('exp reflects configured ttl', function () {
    Config::set('services.jwt.ttl', 60);

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['exp'] - $claims['iat'])->toBe(60);
});

test('throws when JWT_PRIVATE_KEY is missing', function () {
    Config::set('services.jwt.private_key', '');

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    expect(fn () => app(JwtIssuer::class)->issueForUser($user, $workspace))
        ->toThrow(RuntimeException::class);
});

test('accepts base64-encoded private key', function () {
    Config::set('services.jwt.private_key', base64_encode(TestJwtKeys::privateKey()));

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['sub'])->toBe((string) $user->id);
});

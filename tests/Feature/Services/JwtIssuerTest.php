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

test('platform provider embeds llm_provider=platform without extra claims', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['llm_provider'])->toBe('platform');
    expect($claims)->not->toHaveKey('llm_endpoint');
    expect($claims)->not->toHaveKey('llm_model');
    expect($claims)->not->toHaveKey('llm_requires_key');
});

test('anthropic-byo provider embeds model + requires_key flag but NOT api_key', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'anthropic-byo',
        'llm_api_key' => 'sk-ant-secret-key-DO-NOT-LEAK',
        'llm_custom_model' => 'claude-opus-4-7',
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['llm_provider'])->toBe('anthropic-byo');
    expect($claims['llm_model'])->toBe('claude-opus-4-7');
    expect($claims['llm_requires_key'])->toBeTrue();

    // Security: raw api_key must NEVER be in JWT payload
    expect(json_encode($claims))->not->toContain('sk-ant-secret-key-DO-NOT-LEAK');
});

test('openai-compatible provider embeds endpoint + model + requires_key flag', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'openai-compatible',
        'llm_endpoint' => 'http://localhost:11434',
        'llm_api_key' => 'ollama-token',
        'llm_custom_model' => 'qwen2.5:7b',
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['llm_provider'])->toBe('openai-compatible');
    expect($claims['llm_endpoint'])->toBe('http://localhost:11434');
    expect($claims['llm_model'])->toBe('qwen2.5:7b');
    expect($claims['llm_requires_key'])->toBeTrue();
    expect(json_encode($claims))->not->toContain('ollama-token');
});

test('openai-compatible without api_key sets requires_key=false (local ollama)', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'llm_provider_type' => 'openai-compatible',
        'llm_endpoint' => 'http://localhost:11434',
        'llm_api_key' => null,
        'llm_custom_model' => 'llama3.2',
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['llm_requires_key'])->toBeFalse();
});

test('accepts base64-encoded private key', function () {
    Config::set('services.jwt.private_key', base64_encode(TestJwtKeys::privateKey()));

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['sub'])->toBe((string) $user->id);
});

// ---------------------------------------------------------------------------
// Watcher-Token (30-Tage-Daemon für den Conversation-Watcher auf User-Laptop)
// ---------------------------------------------------------------------------

test('issueForWatcher adds watcher scope and uses 30-day TTL by default', function () {
    Config::set('services.jwt.watcher_ttl', 30 * 24 * 3600);

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForWatcher($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['scope'])->toContain('watcher');
    expect($claims['scope'])->toContain('mcp:memory');
    $ttl = (int) ($claims['exp'] - $claims['iat']);
    expect($ttl)->toBe(30 * 24 * 3600);
});

test('issueForWatcher honors JWT_WATCHER_TTL_SECONDS override', function () {
    Config::set('services.jwt.watcher_ttl', 90 * 24 * 3600);

    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForWatcher($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect((int) ($claims['exp'] - $claims['iat']))->toBe(90 * 24 * 3600);
});

test('issueForWatcher preserves admin scope when user is admin', function () {
    // UserFactory hat kein admin()-state — Rolle wird wie im existierenden
    // Test-Pattern (siehe 'includes admin scope for users with admin role')
    // per syncRoles nach Erzeugung zugewiesen.
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::ADMIN]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForWatcher($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['scope'])->toContain('watcher');
    expect($claims['scope'])->toContain('admin');
});

test('issueForUser does NOT get watcher scope', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $token = app(JwtIssuer::class)->issueForUser($user, $workspace);
    $claims = decodeJwtWithTestKey($token);

    expect($claims['scope'])->not->toContain('watcher');
});

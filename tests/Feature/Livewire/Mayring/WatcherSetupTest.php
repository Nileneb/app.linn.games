<?php

use App\Enums\UserRole;
use App\Livewire\Mayring\WatcherSetup;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
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
    Config::set('services.jwt.watcher_ttl', 30 * 24 * 3600);
});

test('requires authentication', function () {
    $this->get(route('mayring.watcher'))
        ->assertRedirect(route('login'));
});

// NB: `User::factory()` triggert automatisch einen default-Workspace via
// Observer (memory-Notiz: User-Observer Auto-Workspace). currentWorkspace()
// gibt den ältesten owned-Workspace zurück — deshalb mutieren wir ihn
// statt einen zweiten zu erzeugen (den die Middleware sonst nie sehen würde).

test('renders setup page for active subscriber', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    $this->actingAs($user)
        ->get(route('mayring.watcher'))
        ->assertOk()
        ->assertSee('Conversation-Watcher einrichten')
        ->assertSee('auf Deinem Rechner')
        ->assertDontSee('MAYRING_JWT=eyJ');
});

test('generate action produces watcher-scoped JWT and exposes Docker command', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    Livewire::actingAs($user)
        ->test(WatcherSetup::class)
        ->assertSet('generatedToken', null)
        ->call('generateToken')
        ->assertSet('generatedToken', fn ($v) => is_string($v) && str_starts_with($v, 'eyJ'))
        ->assertSee('MAYRING_JWT=')
        ->assertSee('docker compose -f mayring-watcher.yml up -d');
});

test('blocks users without active Mayring subscription', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => false]);

    $this->actingAs($user)
        ->get(route('mayring.watcher'))
        ->assertRedirect();
});

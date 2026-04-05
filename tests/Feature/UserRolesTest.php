<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Panel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::all() as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('admin hat Zugriff auf das Filament-Panel', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::ADMIN]);

    expect($user->canAccessPanel(app(Panel::class)))->toBeTrue();
});

test('editor hat keinen Zugriff auf das Filament-Panel', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::EDITOR]);

    expect($user->canAccessPanel(app(Panel::class)))->toBeFalse();
});

test('mitglied hat keinen Zugriff auf das Filament-Panel', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::MITGLIED]);

    expect($user->canAccessPanel(app(Panel::class)))->toBeFalse();
});

test('RoleSeeder legt admin, editor und mitglied an', function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    foreach (UserRole::all() as $roleName) {
        $this->assertDatabaseHas('roles', ['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('admin wird von /admin ins Filament-Dashboard weitergeleitet', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::ADMIN]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

test('nicht-admin wird von /admin auf Login weitergeleitet', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::MITGLIED]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

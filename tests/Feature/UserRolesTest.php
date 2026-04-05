<?php

use App\Models\User;
use Filament\Panel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor',   'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'mitglied', 'guard_name' => 'web']);
});

test('admin hat Zugriff auf das Filament-Panel', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['admin']);

    expect($user->canAccessPanel(app(Panel::class)))->toBeTrue();
});

test('editor hat keinen Zugriff auf das Filament-Panel', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['editor']);

    expect($user->canAccessPanel(app(Panel::class)))->toBeFalse();
});

test('mitglied hat keinen Zugriff auf das Filament-Panel', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['mitglied']);

    expect($user->canAccessPanel(app(Panel::class)))->toBeFalse();
});

test('RoleSeeder legt admin, editor und mitglied an', function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->assertDatabaseHas('roles', ['name' => 'admin',    'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'editor',   'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'mitglied', 'guard_name' => 'web']);
});

<?php

use App\Enums\UserRole;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Policies\ProjektPolicy;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::all() as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('owner can view their project', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $policy = new ProjektPolicy;

    expect($policy->view($user, $projekt))->toBeTrue();
});

test('non-owner cannot view project', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $policy = new ProjektPolicy;

    expect($policy->view($other, $projekt))->toBeFalse();
});

test('owner can update their project', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $policy = new ProjektPolicy;

    expect($policy->update($user, $projekt))->toBeTrue();
});

test('non-owner cannot update project', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $policy = new ProjektPolicy;

    expect($policy->update($other, $projekt))->toBeFalse();
});

test('owner can delete their project', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $policy = new ProjektPolicy;

    expect($policy->delete($user, $projekt))->toBeTrue();
});

test('non-owner cannot delete project', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $policy = new ProjektPolicy;

    expect($policy->delete($other, $projekt))->toBeFalse();
});

test('editor kann Projekt erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::EDITOR]);

    $policy = new ProjektPolicy;

    expect($policy->create($user))->toBeTrue();
});

test('admin kann Projekt erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::ADMIN]);

    $policy = new ProjektPolicy;

    expect($policy->create($user))->toBeTrue();
});

test('mitglied kann kein Projekt erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles([UserRole::MITGLIED]);

    $policy = new ProjektPolicy;

    expect($policy->create($user))->toBeFalse();
});

test('user ohne Rolle kann kein Projekt erstellen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $policy = new ProjektPolicy;

    expect($policy->create($user))->toBeFalse();
});

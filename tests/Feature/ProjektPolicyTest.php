<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Policies\ProjektPolicy;

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

test('any authenticated user can create a project', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $policy = new ProjektPolicy;

    expect($policy->create($user))->toBeTrue();
});

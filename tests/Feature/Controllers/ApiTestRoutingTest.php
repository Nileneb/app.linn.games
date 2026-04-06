<?php

use App\Models\User;

test('can make test request to api', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = test()->actingAs($user)->getJson('/api/user');

    expect($response->status())->toBe(200);
    expect($response->json('id'))->toBe($user->id);
});

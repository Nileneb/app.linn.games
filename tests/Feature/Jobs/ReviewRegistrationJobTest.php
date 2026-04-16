<?php

use App\Jobs\ReviewRegistrationJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.api_key' => 'test-key']);
});

test('job does nothing when user not found', function () {
    ReviewRegistrationJob::dispatchSync(999999999);

    expect(User::find(999999999))->toBeNull();
});

test('job suspends user when Claude returns spam probability >= 0.80', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '0.95']],
        ]),
    ]);

    ReviewRegistrationJob::dispatchSync($user->id);

    expect($user->fresh()->status)->toBe('suspended');
});

test('job does not suspend user when Claude returns low probability', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '0.20']],
        ]),
    ]);

    ReviewRegistrationJob::dispatchSync($user->id);

    expect($user->fresh()->status)->toBe('waitlisted');
});

test('job does not suspend when Claude call throws', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    Http::fake(fn () => throw new \RuntimeException('connection refused'));
    ReviewRegistrationJob::dispatchSync($user->id);
    expect($user->fresh()->status)->toBe('waitlisted');
});

test('job suspends at exact 0.80 threshold', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '0.80']],
        ]),
    ]);
    ReviewRegistrationJob::dispatchSync($user->id);
    expect($user->fresh()->status)->toBe('suspended');
});

test('job does not suspend at 0.79', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '0.79']],
        ]),
    ]);
    ReviewRegistrationJob::dispatchSync($user->id);
    expect($user->fresh()->status)->toBe('waitlisted');
});

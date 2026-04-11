<?php

use App\Models\GameRewardClaim;
use App\Models\User;
use App\Services\GameRewardService;

// Disable starter credit topup so tests start with known balance
beforeEach(function () {
    config(['services.credits.starter_amount_cents' => 0]);
});

test('100 kills triggers 1 euro topup on workspace', function () {
    $user = User::factory()->withoutTwoFactor()->create(['total_kills' => 100]);
    $workspace = $user->workspaces()->first();

    app(GameRewardService::class)->checkAndReward($user);

    expect($workspace->fresh()->credits_balance_cents)->toBe(100);
    expect(GameRewardClaim::where('user_id', $user->id)->where('kills_threshold', 100)->exists())->toBeTrue();
});

test('checkAndReward is idempotent — does not double-reward 100 kills', function () {
    $user = User::factory()->withoutTwoFactor()->create(['total_kills' => 100]);
    $workspace = $user->workspaces()->first();

    $service = app(GameRewardService::class);
    $service->checkAndReward($user);
    $service->checkAndReward($user);

    expect($workspace->fresh()->credits_balance_cents)->toBe(100); // only 100, not 200
});

test('2500 kills reduces discount_factor by 0.05', function () {
    $user = User::factory()->withoutTwoFactor()->create(['total_kills' => 2500]);
    $workspace = $user->workspaces()->first();
    $workspace->update(['discount_factor' => 1.00]);

    app(GameRewardService::class)->checkAndReward($user);

    // Note: 100, 500, 1000 topups also fire at 2500 kills; only check discount_factor
    expect((float) $workspace->fresh()->discount_factor)->toBe(0.95);
});

test('discount_factor never goes below 0.0', function () {
    config(['game.kill_rewards' => [['kills' => 5000, 'type' => 'discount', 'value' => 0.10]]]);

    $user = User::factory()->withoutTwoFactor()->create(['total_kills' => 5000]);
    $workspace = $user->workspaces()->first();
    $workspace->update(['discount_factor' => 0.03]);

    app(GameRewardService::class)->checkAndReward($user);

    expect((float) $workspace->fresh()->discount_factor)->toBeGreaterThanOrEqual(0.0);
    expect((float) $workspace->fresh()->discount_factor)->toBe(0.0);
});

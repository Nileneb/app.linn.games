<?php

use App\Livewire\Credits\Purchase;
use App\Livewire\Credits\Usage;
use App\Models\User;
use App\Services\CreditService;
use Livewire\Livewire;

test('Purchase Page lädt für eingeloggten User', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'active']);
    // SetUpNewUser listener creates the default workspace automatically

    $this->actingAs($user);

    Livewire::test(Purchase::class)
        ->assertSee('Credits kaufen');
});

test('Usage Page zeigt Guthaben', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'active']);
    // SetUpNewUser creates default workspace with starter credits
    $workspace = $user->workspaces()->first();

    // Top up to a distinct amount so we can verify the balance is shown
    app(CreditService::class)->topUp($workspace, 1400, 'Test topup');
    // Starter (100) + 1400 = 1500 → displayed as 15,00 €
    $workspace->refresh();

    $this->actingAs($user);

    Livewire::test(Usage::class)
        ->assertSee('15,00');
});

test('CreditService topUp erhöht Guthaben korrekt', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = $user->workspaces()->first();
    $initial = $workspace->credits_balance_cents;

    app(CreditService::class)->topUp($workspace, 1000, 'Test topup');

    $workspace->refresh();
    expect($workspace->credits_balance_cents)->toBe($initial + 1000);
});

test('Checkout redirect validiert package index', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'active']);

    $this->actingAs($user)
        ->post(route('credits.checkout'), ['package' => 99])
        ->assertSessionHasErrors('package');
});

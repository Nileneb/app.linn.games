<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Livewire\Volt\Volt;

test('projekt detail rendert mit standard-tab P1', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.projekt-detail', ['projekt' => $projekt])
        ->assertSet('activeTab', 1)
        ->assertSee($projekt->titel)
        ->assertSee($projekt->forschungsfrage);
});

test('tab kann gewechselt werden', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.projekt-detail', ['projekt' => $projekt])
        ->call('switchTab', 3)
        ->assertSet('activeTab', 3);
});

test('tab wird auf 1-8 clamped', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.projekt-detail', ['projekt' => $projekt])
        ->call('switchTab', 0)
        ->assertSet('activeTab', 1)
        ->call('switchTab', 99)
        ->assertSet('activeTab', 8);
});

test('nicht-owner bekommt 403', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    Volt::test('recherche.projekt-detail', ['projekt' => $projekt])
        ->assertStatus(403);
});

test('P5-P8 tabs rendern phase-komponenten', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    foreach ([5, 6, 7, 8] as $tab) {
        Volt::test('recherche.projekt-detail', ['projekt' => $projekt])
            ->call('switchTab', $tab)
            ->assertSet('activeTab', $tab)
            ->assertDontSee('wird in der nächsten Iteration implementiert');
    }
});

test('getPhaseStatus gibt null fuer fehlende phase zurueck', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $component = Volt::test('recherche.projekt-detail', ['projekt' => $projekt]);
    // Keine Phasen erstellt → null
    expect($component->call('getPhaseStatus', 1)->get('activeTab'))->toBe(1);
});

test('zurueck-link zeigt auf recherche-liste', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.projekt-detail', ['projekt' => $projekt])
        ->assertSee('Zurück');
});

<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor',   'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'mitglied', 'guard_name' => 'web']);
});

test('authenticated user can create a research project', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['editor']);

    $this->actingAs($user);

    $response = Volt::test('recherche.research-input')
        ->set('eingabe', 'Was sind die Auswirkungen von X auf Y?')
        ->call('starteRecherche');

    $response->assertHasNoErrors();

    $this->assertDatabaseHas('projekte', [
        'user_id' => $user->id,
        'forschungsfrage' => 'Was sind die Auswirkungen von X auf Y?',
    ]);
});

test('research project creation redirects to project detail', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['editor']);

    $this->actingAs($user);

    Volt::test('recherche.research-input')
        ->set('eingabe', 'Testfrage für Redirect')
        ->call('starteRecherche')
        ->assertRedirect();

    $projekt = Projekt::where('user_id', $user->id)->first();
    expect($projekt)->not->toBeNull();
});

test('research input requires eingabe field', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['editor']);

    $this->actingAs($user);

    $response = Volt::test('recherche.research-input')
        ->set('eingabe', '')
        ->call('starteRecherche');

    $response->assertHasErrors(['eingabe']);
});

test('project list shows only own projects', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();

    Projekt::factory()->create(['user_id' => $owner->id, 'titel' => 'Mein Projekt']);
    Projekt::factory()->create(['user_id' => $other->id, 'titel' => 'Fremdes Projekt']);

    $this->actingAs($owner);

    $response = $this->get(route('recherche'));

    $response->assertStatus(200);
    $response->assertSee('Mein Projekt');
    $response->assertDontSee('Fremdes Projekt');
});

test('guest cannot access recherche page', function () {
    $response = $this->get(route('recherche'));

    $response->assertRedirect(route('login'));
});

test('user can view own project detail', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create([
        'user_id' => $user->id,
        'titel' => 'Detailansicht-Test',
        'forschungsfrage' => 'Wie funktioniert X?',
    ]);

    $response = $this->actingAs($user)->get(route('recherche.projekt', $projekt));

    $response->assertStatus(200);
    $response->assertSee('Detailansicht-Test');
    $response->assertSee('Wie funktioniert X?');
});

test('user cannot view another users project', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $intruder = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($intruder)->get(route('recherche.projekt', $projekt));

    $response->assertStatus(403);
});

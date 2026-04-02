<?php

use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($this->user);
});

test('appearance: rendert alle drei buttons mit data-mode attribut', function () {
    Volt::test('settings.appearance')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System')
        ->assertSeeHtml('data-mode="light"')
        ->assertSeeHtml('data-mode="dark"')
        ->assertSeeHtml('data-mode="system"');
});

test('appearance: rendert appearance-toggle container', function () {
    Volt::test('settings.appearance')
        ->assertSeeHtml('id="appearance-toggle"');
});

test('appearance: rendert client-seitiges script', function () {
    Volt::test('settings.appearance')
        ->assertSeeHtml('localStorage.getItem')
        ->assertSeeHtml('setAppearance');
});

test('appearance: seite ist nur fuer eingeloggte user erreichbar', function () {
    auth()->logout();
    $this->get(route('appearance.edit'))
        ->assertRedirect(route('login'));
});

test('appearance: seite laesst sich aufrufen', function () {
    $this->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Appearance');
});

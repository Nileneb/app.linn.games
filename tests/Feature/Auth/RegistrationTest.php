<?php

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('register:127.0.0.1'));

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertStatus(200);
});

test('registrierung erstellt pending registration und kein user', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'forschungsfrage' => 'Welche Auswirkungen hat KI auf den Bildungsbereich?',
        'forschungsbereich' => 'Bildung & Pädagogik',
        'erfahrung' => 'Ja, 1–2 Mal',
        '_timing' => 5000,
        '_tz' => 'Europe/Berlin',
    ]);

    $response->assertRedirect(route('register'))
        ->assertSessionHas('status', 'verification-link-sent');

    $this->assertGuest();
    expect(PendingRegistration::where('email', 'test@example.com')->exists())->toBeTrue();
    expect(\App\Models\User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registrierung schlägt fehl wenn name fehlt', function () {
    $this->post(route('register.store'), [
        'email' => 'missing@name.de',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('name');

    $this->assertGuest();
});

test('registrierung schlägt fehl bei ungültiger email', function () {
    $this->post(route('register.store'), [
        'name' => 'Kein Valid',
        'email' => 'kein-gueltiges-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('registrierung schlägt fehl bei bereits genutzter email', function () {
    \App\Models\User::factory()->withoutTwoFactor()->create(['email' => 'doppelt@example.de']);

    $this->post(route('register.store'), [
        'name' => 'Zweiter',
        'email' => 'doppelt@example.de',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('registrierung schlägt fehl bei nicht übereinstimmenden passwörtern', function () {
    $this->post(route('register.store'), [
        'name' => 'Kein Match',
        'email' => 'nomatch@example.de',
        'password' => 'password',
        'password_confirmation' => 'anders123',
    ])->assertSessionHasErrors('password');
});

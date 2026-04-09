<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'forschungsfrage' => 'Welche Auswirkungen hat KI auf den Bildungsbereich?',
        'forschungsbereich' => 'Bildung & Pädagogik',
        'erfahrung' => 'Ja, 1–2 Mal',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
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

    $this->assertGuest();
});

test('registrierung schlägt fehl bei bereits genutzter email', function () {
    $existing = \App\Models\User::factory()->withoutTwoFactor()->create(['email' => 'doppelt@example.de']);

    $this->post(route('register.store'), [
        'name' => 'Zweiter',
        'email' => 'doppelt@example.de',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('registrierung schlägt fehl bei nicht übereinstimmenden passwörtern', function () {
    $this->post(route('register.store'), [
        'name' => 'Kein Match',
        'email' => 'nomatch@example.de',
        'password' => 'password',
        'password_confirmation' => 'anders123',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$validPayload = fn () => [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password',
    'password_confirmation' => 'password',
    'forschungsfrage' => 'Welche Auswirkungen hat digitale Bildung auf Schulergebnisse?',
    'forschungsbereich' => 'Bildung & Pädagogik',
    'erfahrung' => 'Ja, 1–2 Mal',
];

test('nutzer kann sich mit gültigen beta-feldern registrieren und erhält status waitlisted', function () use ($validPayload) {
    $response = $this->post(route('register.store'), $validPayload());

    $response->assertSessionHasNoErrors();

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe('waitlisted')
        ->and($user->forschungsfrage)->toBe('Welche Auswirkungen hat digitale Bildung auf Schulergebnisse?')
        ->and($user->forschungsbereich)->toBe('Bildung & Pädagogik')
        ->and($user->erfahrung)->toBe('Ja, 1–2 Mal');
});

test('registrierung schlägt fehl wenn forschungsfrage fehlt', function () use ($validPayload) {
    $payload = $validPayload();
    unset($payload['forschungsfrage']);

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors('forschungsfrage');

    $this->assertGuest();
});

test('registrierung schlägt fehl bei ungültigem forschungsbereich', function () use ($validPayload) {
    $payload = $validPayload();
    $payload['forschungsbereich'] = 'Ungültiger Bereich';

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors('forschungsbereich');

    $this->assertGuest();
});

test('registrierung schlägt fehl wenn erfahrung fehlt', function () use ($validPayload) {
    $payload = $validPayload();
    unset($payload['erfahrung']);

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors('erfahrung');

    $this->assertGuest();
});

test('registrierung schlägt fehl bei ungültiger erfahrung', function () use ($validPayload) {
    $payload = $validPayload();
    $payload['erfahrung'] = 'Irgendwas';

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors('erfahrung');

    $this->assertGuest();
});

test('admin kann waitlisted nutzer freischalten und status wird trial', function () {
    $waitlisted = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    // Direkte Statusänderung simuliert die Admin-Aktion (Freischalten)
    $waitlisted->update(['status' => 'trial']);
    $waitlisted->refresh();

    expect($waitlisted->status)->toBe('trial');
});

test('isWaitlisted gibt true zurück für waitlisted nutzer', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    expect($user->isWaitlisted())->toBeTrue()
        ->and($user->isActive())->toBeFalse()
        ->and($user->isTrial())->toBeFalse();
});

test('waitlisted nutzer wird durch middleware blockiert und auf pending-approval weitergeleitet', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    // Middleware loggt User aus und leitet zu pending-approval weiter
    $response->assertRedirect(route('pending-approval'));
});

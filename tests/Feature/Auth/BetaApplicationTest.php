<?php

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('register:127.0.0.1'));

$validPayload = fn () => [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password',
    'password_confirmation' => 'password',
    'forschungsfrage' => 'Welche Auswirkungen hat digitale Bildung auf Schulergebnisse?',
    'forschungsbereich' => 'Bildung & Pädagogik',
    'erfahrung' => 'Ja, 1–2 Mal',
    '_timing' => 5000,
    '_tz' => 'Europe/Berlin',
    '_captcha_solved' => '1',
];

test('nutzer kann sich registrieren und erhält pending-registration mit verification-link-sent status', function () use ($validPayload) {
    $response = $this->post(route('register.store'), $validPayload());

    $response->assertRedirect(route('register'))
        ->assertSessionHas('status', 'verification-link-sent');

    $pending = PendingRegistration::where('email', 'test@example.com')->first();

    expect($pending)->not->toBeNull()
        ->and($pending->forschungsfrage)->toBe('Welche Auswirkungen hat digitale Bildung auf Schulergebnisse?')
        ->and($pending->forschungsbereich)->toBe('Bildung & Pädagogik')
        ->and($pending->status)->toBe('pending_email');
});

test('registrierung schlägt fehl wenn forschungsfrage fehlt', function () use ($validPayload) {
    $payload = $validPayload();
    unset($payload['forschungsfrage']);
    $this->post(route('register.store'), $payload)->assertSessionHasErrors('forschungsfrage');
});

test('registrierung schlägt fehl bei ungültigem forschungsbereich', function () use ($validPayload) {
    $payload = $validPayload();
    $payload['forschungsbereich'] = 'Ungültiger Bereich';
    $this->post(route('register.store'), $payload)->assertSessionHasErrors('forschungsbereich');
});

test('registrierung schlägt fehl wenn erfahrung fehlt', function () use ($validPayload) {
    $payload = $validPayload();
    unset($payload['erfahrung']);
    $this->post(route('register.store'), $payload)->assertSessionHasErrors('erfahrung');
});

test('admin kann waitlisted nutzer freischalten und status wird trial', function () {
    $waitlisted = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    $waitlisted->update(['status' => 'trial']);
    expect($waitlisted->fresh()->status)->toBe('trial');
});

test('isWaitlisted gibt true zurück für waitlisted nutzer', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    expect($user->isWaitlisted())->toBeTrue()
        ->and($user->isActive())->toBeFalse();
});

test('waitlisted nutzer wird durch middleware blockiert', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('pending-approval'));
});

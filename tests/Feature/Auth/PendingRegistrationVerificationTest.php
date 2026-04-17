<?php

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makePending(array $overrides = []): PendingRegistration
{
    return PendingRegistration::create(array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'forschungsfrage' => 'Forschungsfrage',
        'forschungsbereich' => 'Sonstiges',
        'erfahrung' => 'Ja, 1–2 Mal',
        'token' => Str::uuid()->toString(),
        'token_expires_at' => now()->addHours(24),
        'expires_at' => now()->addHours(48),
        'status' => 'pending_email',
    ], $overrides));
}

test('gültiger token erstellt user und leitet zu login weiter', function () {
    $pending = makePending();

    $this->get(route('register.verify', $pending->token))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'email-verified');

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'test@example.com')->first()->status)->toBe('waitlisted')
        ->and(PendingRegistration::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('ungültiger token leitet zu register mit fehler weiter', function () {
    $this->get(route('register.verify', Str::uuid()))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');
});

test('abgelaufener token löscht pending und zeigt fehler', function () {
    $pending = makePending(['token_expires_at' => now()->subMinute()]);

    $this->get(route('register.verify', $pending->token))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    expect(PendingRegistration::find($pending->id))->toBeNull();
});

test('bereits verwendeter token (status != pending_email) gibt fehler', function () {
    $pending = makePending(['status' => 'verified']);

    $this->get(route('register.verify', $pending->token))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');
});

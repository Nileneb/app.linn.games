<?php

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('pending registration kann angelegt werden', function () {
    $pending = PendingRegistration::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'forschungsfrage' => 'Frage',
        'forschungsbereich' => 'Sonstiges',
        'erfahrung' => 'Ja, 1–2 Mal',
        'token' => Str::uuid()->toString(),
        'token_expires_at' => now()->addHours(24),
        'expires_at' => now()->addHours(48),
    ]);

    expect($pending->confidence_score)->toBe(0)
        ->and($pending->status)->toBe('pending_email')
        ->and($pending->needs_review)->toBeFalse();
});

test('isExpired gibt true zurück wenn token_expires_at vergangen', function () {
    $pending = PendingRegistration::create([
        'name' => 'Test',
        'email' => 'expired@example.com',
        'password' => bcrypt('password'),
        'forschungsfrage' => 'Frage',
        'forschungsbereich' => 'Sonstiges',
        'erfahrung' => 'Ja, 1–2 Mal',
        'token' => Str::uuid()->toString(),
        'token_expires_at' => now()->subMinute(),
        'expires_at' => now()->addHours(48),
    ]);

    expect($pending->isExpired())->toBeTrue();
});

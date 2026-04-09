<?php

use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditService;
use Illuminate\Support\Facades\Log;

test('SetUpNewUser erstellt Workspace und Startguthaben bei neuer User-Erstellung', function () {
    config(['services.credits.starter_amount_cents' => 100]);

    $user = User::factory()->withoutTwoFactor()->create();

    expect(Workspace::where('owner_id', $user->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('credit_transactions', [
        'type' => 'topup',
        'amount_cents' => 100,
        'description' => 'Startguthaben',
    ]);
});

test('SetUpNewUser überspringt topUp wenn starter_amount_cents null ist', function () {
    config(['services.credits.starter_amount_cents' => 0]);

    $user = User::factory()->withoutTwoFactor()->create();

    expect(Workspace::where('owner_id', $user->id)->exists())->toBeTrue();

    $this->assertDatabaseMissing('credit_transactions', [
        'description' => 'Startguthaben',
    ]);
});

test('SetUpNewUser loggt Fehler und lässt User-Erstellung durch wenn CreditService fehlschlägt', function () {
    Log::spy();

    $this->mock(CreditService::class, function ($mock) {
        $mock->shouldReceive('topUp')->andThrow(new \RuntimeException('DB unavailable'));
    });

    $user = User::factory()->withoutTwoFactor()->create();

    // User wurde trotzdem angelegt
    $this->assertDatabaseHas('users', ['id' => $user->id]);

    // Fehler wurde geloggt
    Log::shouldHaveReceived('error')
        ->once()
        ->with('User-Initialisierung fehlgeschlagen', \Mockery::subset([
            'user_id' => $user->id,
            'error' => 'DB unavailable',
        ]));
});

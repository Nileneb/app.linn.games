<?php

use App\Livewire\Auth\AcceptInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('valid invitation token shows acceptance form', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'status' => 'invited',
        'invitation_token' => $token = Str::random(64),
        'invitation_expires_at' => now()->addDays(28),
    ]);

    $response = $this->get(route('invitation.accept', ['token' => $token]));
    $response->assertStatus(200);
    $response->assertSee($user->name);
});

test('expired invitation token shows error', function () {
    User::factory()->withoutTwoFactor()->create([
        'status' => 'invited',
        'invitation_token' => $token = Str::random(64),
        'invitation_expires_at' => now()->subDays(1),
    ]);

    $response = $this->get(route('invitation.accept', ['token' => $token]));
    $response->assertStatus(200);
    $response->assertSee('ungültig');
});

test('accepting invitation sets password and status to trial', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'status' => 'invited',
        'invitation_token' => $token = Str::random(64),
        'invitation_expires_at' => now()->addDays(28),
        'password' => 'old-hashed-value',
    ]);

    Livewire::test(AcceptInvitation::class, ['token' => $token])
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('accept')
        ->assertRedirect(route('login'));

    $user->refresh();
    expect($user->status)->toBe('active');
    expect($user->invitation_token)->toBeNull();
});

test('cleanup command deletes expired invited users', function () {
    $expired = User::factory()->withoutTwoFactor()->create([
        'status' => 'invited',
        'invitation_token' => Str::random(64),
        'invitation_expires_at' => now()->subDays(1),
    ]);
    $valid = User::factory()->withoutTwoFactor()->create([
        'status' => 'invited',
        'invitation_token' => Str::random(64),
        'invitation_expires_at' => now()->addDays(28),
    ]);

    $this->artisan('invitations:cleanup')->assertExitCode(0);

    expect(User::find($expired->id))->toBeNull();
    expect(User::find($valid->id))->not->toBeNull();
});

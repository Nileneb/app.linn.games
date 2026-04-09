<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

test('redirect route returns redirect to github', function () {
    $response = $this->get(route('auth.github'));
    $response->assertRedirect();
    expect($response->headers->get('location'))->toContain('github.com');
});

test('callback creates new waitlisted user and redirects to pending-approval', function () {
    $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $socialiteUser->allows('getId')->andReturn('github-123');
    $socialiteUser->allows('getEmail')->andReturn('newoauth@example.com');
    $socialiteUser->allows('getName')->andReturn('OAuth User');
    $socialiteUser->allows('getNickname')->andReturn('oauthuser');
    $socialiteUser->allows('getRaw')->andReturn([]);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get(route('auth.github.callback'))
        ->assertRedirect(route('pending-approval'));

    $this->assertDatabaseHas('users', [
        'email' => 'newoauth@example.com',
        'provider' => 'github',
        'provider_id' => 'github-123',
        'status' => 'waitlisted',
    ]);
});

test('callback logs in existing active user by provider_id', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'provider' => 'github',
        'provider_id' => 'github-456',
        'status' => 'active',
    ]);

    $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $socialiteUser->allows('getId')->andReturn('github-456');
    $socialiteUser->allows('getEmail')->andReturn($user->email);
    $socialiteUser->allows('getRaw')->andReturn([]);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get(route('auth.github.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('callback links github to existing user found by email', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'active']);

    $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $socialiteUser->allows('getId')->andReturn('github-789');
    $socialiteUser->allows('getEmail')->andReturn($user->email);
    $socialiteUser->allows('getName')->andReturn($user->name);
    $socialiteUser->allows('getRaw')->andReturn(['email_verified' => true]);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get(route('auth.github.callback'))
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->provider)->toBe('github');
    expect($user->provider_id)->toBe('github-789');
    $this->assertAuthenticatedAs($user);
});

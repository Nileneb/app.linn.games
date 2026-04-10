<?php

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('authenticated user can create a game session', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($user);

    $res = $this->postJson('/game/sessions');

    $res->assertOk()->assertJsonStructure(['code']);
    expect($res->json('code'))->toHaveLength(6);
    $this->assertDatabaseHas('game_session_players', ['user_id' => $user->id]);
});

test('user can join an existing session', function () {
    $host = User::factory()->withoutTwoFactor()->create();
    $joiner = User::factory()->withoutTwoFactor()->create();
    $session = GameSession::create([
        'code' => 'ABC123',
        'host_user_id' => $host->id,
        'status' => 'waiting',
    ]);
    DB::table('game_session_players')->insert([
        'session_id' => $session->id,
        'user_id' => $host->id,
        'joined_at' => now(),
    ]);

    $this->actingAs($joiner);
    $this->postJson('/game/sessions/ABC123/join')
        ->assertOk()
        ->assertJsonFragment(['code' => 'ABC123', 'is_host' => false]);

    $this->assertDatabaseHas('game_session_players', [
        'session_id' => $session->id,
        'user_id' => $joiner->id,
    ]);
});

test('session rejects more than 10 players', function () {
    $users = User::factory()->withoutTwoFactor()->count(11)->create();
    $session = GameSession::create([
        'code' => 'FULL00',
        'host_user_id' => $users[0]->id,
        'status' => 'waiting',
    ]);
    foreach ($users->take(10) as $u) {
        DB::table('game_session_players')->insert([
            'session_id' => $session->id,
            'user_id' => $u->id,
            'joined_at' => now(),
        ]);
    }

    $this->actingAs($users[10]);
    $this->postJson('/game/sessions/FULL00/join')->assertStatus(422);
});

test('guest cannot create session', function () {
    $this->postJson('/game/sessions')->assertUnauthorized();
});

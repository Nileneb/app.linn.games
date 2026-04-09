<?php

use App\Models\Recherche\Projekt;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->user->id]);
});

test('authenticated user can access galaxy page', function () {
    $this->actingAs($this->user)
        ->get(route('recherche.galaxy', $this->projekt))
        ->assertOk()
        ->assertViewIs('galaxy.show')
        ->assertViewHas('projektId', $this->projekt->id);
});

test('unauthenticated user is redirected from galaxy page', function () {
    $this->get(route('recherche.galaxy', $this->projekt))
        ->assertRedirect(route('login'));
});

test('user without workspace access gets 403 on galaxy page', function () {
    $other = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($other)
        ->get(route('recherche.galaxy', $this->projekt))
        ->assertForbidden();
});

test('galaxy data endpoint returns 404 json when no file exists', function () {
    $path = public_path("galaxy-data/{$this->projekt->id}.json");
    if (file_exists($path)) {
        unlink($path);
    }

    $this->actingAs($this->user)
        ->getJson(route('recherche.galaxy-data', $this->projekt))
        ->assertNotFound()
        ->assertJson(['error' => 'No galaxy data available. Run: php artisan galaxy:generate '.$this->projekt->id]);
});

test('galaxy data endpoint returns json when file exists', function () {
    $dir = public_path('galaxy-data');
    $path = "{$dir}/{$this->projekt->id}.json";
    @mkdir($dir, 0755, true);
    file_put_contents($path, json_encode(['meta' => ['cluster_count' => 3], 'clusters' => []]));

    $this->actingAs($this->user)
        ->getJson(route('recherche.galaxy-data', $this->projekt))
        ->assertOk()
        ->assertJsonStructure(['meta', 'clusters']);

    unlink($path);
});

test('galaxy data endpoint returns 403 for user without access', function () {
    $other = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($other)
        ->getJson(route('recherche.galaxy-data', $this->projekt))
        ->assertForbidden();
});

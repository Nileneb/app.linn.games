<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->withoutTwoFactor()->create();
    $this->nonOwner = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->owner->id]);
    Storage::fake('local');
});

test('md viewer displays markdown files', function () {
    Storage::disk('local')->makeDirectory("recherche/{$this->projekt->id}/screening", true);
    Storage::disk('local')->put(
        "recherche/{$this->projekt->id}/screening/results.md",
        "# Screening Results\n\nThis is the screening report."
    );

    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(200);
    $response->assertSee('<h1>Screening Results</h1>', false);
    $response->assertSee('This is the screening report.');
});

test('md viewer enforces projekt policy', function () {
    Storage::disk('local')->makeDirectory("recherche/{$this->projekt->id}/screening", true);
    Storage::disk('local')->put(
        "recherche/{$this->projekt->id}/screening/results.md",
        '# Screening Results'
    );

    $response = $this->actingAs($this->nonOwner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(403);
});

test('md viewer returns 404 for missing files', function () {
    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(404);
});

test('md viewer handles multiple markdown files', function () {
    Storage::disk('local')->makeDirectory("recherche/{$this->projekt->id}/screening", true);
    Storage::disk('local')->put("recherche/{$this->projekt->id}/screening/file1.md", "# File 1\n\nContent 1");
    Storage::disk('local')->put("recherche/{$this->projekt->id}/screening/file2.md", "# File 2\n\nContent 2");
    Storage::disk('local')->put("recherche/{$this->projekt->id}/screening/data.txt", 'Some text file');

    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(200)
        ->assertSee('File 1')
        ->assertSee('Content 1')
        ->assertSee('File 2')
        ->assertSee('Content 2')
        ->assertDontSee('Some text file');
});

test('md viewer rejects invalid phases', function () {
    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/invalid_phase");

    $response->assertStatus(404);
});

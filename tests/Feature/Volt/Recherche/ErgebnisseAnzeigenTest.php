<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->owner->id]);
});

test('md viewer displays markdown files', function () {
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
    $nonOwner = User::factory()->withoutTwoFactor()->create();

    Storage::disk('local')->put(
        "recherche/{$this->projekt->id}/screening/results.md",
        "# Screening Results"
    );

    $response = $this->actingAs($nonOwner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(403);
});

test('md viewer returns 404 for missing files', function () {
    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(404);
});

test('md viewer handles multiple markdown files', function () {
    Storage::disk('local')->put(
        "recherche/{$this->projekt->id}/screening/report.md",
        "# Screening Report\n\nFirst report."
    );
    Storage::disk('local')->put(
        "recherche/{$this->projekt->id}/screening/summary.md",
        "# Summary\n\nSecond summary."
    );
    Storage::disk('local')->put(
        "recherche/{$this->projekt->id}/screening/data.txt",
        "Some text file"
    );

    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/screening");

    $response->assertStatus(200);
    $response->assertSee('<h1>Screening Report</h1>', false);
    $response->assertSee('First report.');
    $response->assertSee('<h1>Summary</h1>', false);
    $response->assertSee('Second summary.');
    $response->assertDontSee('Some text file');
});

test('md viewer rejects invalid phases', function () {
    $response = $this->actingAs($this->owner)
        ->get("/recherche/{$this->projekt->id}/ergebnisse/invalid-phase");

    $response->assertStatus(404);
});

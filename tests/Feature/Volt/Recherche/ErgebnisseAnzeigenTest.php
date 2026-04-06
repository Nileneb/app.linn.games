<?php

namespace Tests\Feature\Volt\Recherche;

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ErgebnisseAnzeigenTest extends TestCase
{
    use RefreshDatabase;

    public function test_md_viewer_displays_markdown_files(): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

        // Don't use fake storage - use real storage
        $dir = storage_path("app/recherche/{$projekt->id}/screening");
        @mkdir($dir, 0755, true);
        file_put_contents("$dir/results.md", "# Screening Results\n\nThis is the screening report.");

        try {
            $response = $this->actingAs($owner)
                ->get("/recherche/{$projekt->id}/ergebnisse/screening");

            $response->assertStatus(200);
            $response->assertSee('<h1>Screening Results</h1>');
            $response->assertSee('This is the screening report.');
        } finally {
            // Cleanup
            @unlink("$dir/results.md");
            @rmdir($dir);
        }
    }

    public function test_md_viewer_enforces_projekt_policy(): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $nonOwner = User::factory()->withoutTwoFactor()->create();
        $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

        // Don't use fake storage - use real storage
        $dir = storage_path("app/recherche/{$projekt->id}/screening");
        @mkdir($dir, 0755, true);
        file_put_contents("$dir/results.md", "# Screening Results");

        try {
            $response = $this->actingAs($nonOwner)
                ->get("/recherche/{$projekt->id}/ergebnisse/screening");

            $response->assertStatus(403);
        } finally {
            // Cleanup
            @unlink("$dir/results.md");
            @rmdir($dir);
        }
    }

    public function test_md_viewer_returns_404_for_missing_files(): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->get("/recherche/{$projekt->id}/ergebnisse/screening");

        $response->assertStatus(404);
    }

    public function test_md_viewer_handles_multiple_markdown_files(): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $projekt = Projekt::factory()->create(['user_id' => $owner->id]);

        // Don't use fake storage - use real storage
        $dir = storage_path("app/recherche/{$projekt->id}/screening");
        @mkdir($dir, 0755, true);
        file_put_contents("$dir/report.md", "# Screening Report\n\nFirst report.");
        file_put_contents("$dir/summary.md", "# Summary\n\nSecond summary.");
        file_put_contents("$dir/data.txt", "Some text file");

        try {
            $response = $this->actingAs($owner)
                ->get("/recherche/{$projekt->id}/ergebnisse/screening");

            $response->assertStatus(200);
            $response->assertSee('<h1>Screening Report</h1>');
            $response->assertSee('First report.');
            $response->assertSee('<h1>Summary</h1>');
            $response->assertSee('Second summary.');
            $response->assertDontSee('Some text file');
        } finally {
            // Cleanup
            @unlink("$dir/report.md");
            @unlink("$dir/summary.md");
            @unlink("$dir/data.txt");
            @rmdir($dir);
            @rmdir(dirname($dir));
        }
    }
}

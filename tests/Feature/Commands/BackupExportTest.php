<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('backup:export erzeugt eine ndjson-datei im out-dir', function () {
    $user = User::factory()->withoutTwoFactor()->create(['name' => 'Test User']);

    $outDir = sys_get_temp_dir().'/backup_test_'.uniqid();

    $this->artisan('backup:export', ['--tables' => 'users', '--out-dir' => $outDir])
        ->assertSuccessful();

    $files = glob($outDir.'/backup_*.ndjson');
    expect($files)->toHaveCount(1);

    $lines = array_filter(explode("\n", file_get_contents($files[0])));
    $meta = json_decode(array_values($lines)[0], true);

    expect($meta['__meta']['table'])->toBe('users');
    expect($meta['__meta']['schema_version'])->toBe(1);
    expect($meta['__meta']['columns'])->toBeArray()->not->toBeEmpty();

    // Cleanup
    array_map('unlink', $files);
    rmdir($outDir);
});

test('backup:export enthält die erstellten user-datensätze', function () {
    $user = User::factory()->withoutTwoFactor()->create(['name' => 'Export Tester', 'email' => 'export@test.de']);

    $outDir = sys_get_temp_dir().'/backup_test_'.uniqid();

    $this->artisan('backup:export', ['--tables' => 'users', '--out-dir' => $outDir])
        ->assertSuccessful();

    $files = glob($outDir.'/backup_*.ndjson');
    $lines = array_values(array_filter(explode("\n", file_get_contents($files[0]))));

    // First line is __meta, remaining are data rows
    $dataLines = array_slice($lines, 1);
    $ids = array_map(fn ($l) => json_decode($l, true)['id'] ?? null, $dataLines);

    expect($ids)->toContain($user->id);

    array_map('unlink', $files);
    rmdir($outDir);
});

test('backup:export überspringt nicht existierende tabellen mit warnung', function () {
    $outDir = sys_get_temp_dir().'/backup_test_'.uniqid();

    $this->artisan('backup:export', ['--tables' => 'nicht_vorhanden', '--out-dir' => $outDir])
        ->expectsOutputToContain('does not exist')
        ->assertSuccessful();

    $files = glob($outDir.'/backup_*.ndjson');
    expect($files)->toHaveCount(1);

    array_map('unlink', $files);
    rmdir($outDir);
});

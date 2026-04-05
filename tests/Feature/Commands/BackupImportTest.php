<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

function writeNdjson(string $path, string $table, array $columns, array $rows): void
{
    $lines = [];
    $lines[] = json_encode([
        '__meta' => [
            'schema_version' => 1,
            'exported_at'    => now()->toIso8601String(),
            'table'          => $table,
            'columns'        => $columns,
        ],
    ]);

    foreach ($rows as $row) {
        $lines[] = json_encode($row);
    }

    file_put_contents($path, implode("\n", $lines) . "\n");
}

test('backup:import importiert einen user idempotent', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Export the row as NDJSON, then delete the user, then import
    $columns = DB::getSchemaBuilder()->getColumnListing('users');
    $row     = (array) DB::table('users')->where('id', $user->id)->first();

    $file = sys_get_temp_dir() . '/import_test_' . uniqid() . '.ndjson';
    writeNdjson($file, 'users', $columns, [$row]);

    DB::table('users')->where('id', $user->id)->delete();
    expect(DB::table('users')->where('id', $user->id)->exists())->toBeFalse();

    $this->artisan('backup:import', ['--file' => $file])
        ->assertSuccessful();

    expect(DB::table('users')->where('id', $user->id)->exists())->toBeTrue();

    // Second import — must not crash (idempotent)
    $this->artisan('backup:import', ['--file' => $file])
        ->assertSuccessful();

    unlink($file);
});

test('backup:import ignoriert unbekannte spalten aus dem export', function () {
    $user    = User::factory()->withoutTwoFactor()->create();
    $columns = DB::getSchemaBuilder()->getColumnListing('users');
    $row     = (array) DB::table('users')->where('id', $user->id)->first();

    // Add a column that doesn't exist in the current schema
    $columnsWithExtra = array_merge($columns, ['old_column_no_longer_exists']);
    $rowWithExtra     = array_merge($row, ['old_column_no_longer_exists' => 'stale_value']);

    $file = sys_get_temp_dir() . '/import_test_' . uniqid() . '.ndjson';
    writeNdjson($file, 'users', $columnsWithExtra, [$rowWithExtra]);

    DB::table('users')->where('id', $user->id)->delete();

    $this->artisan('backup:import', ['--file' => $file])
        ->expectsOutputToContain('dropped (not in DB)')
        ->assertSuccessful();

    expect(DB::table('users')->where('id', $user->id)->exists())->toBeTrue();

    unlink($file);
});

test('backup:import dry-run schreibt nichts in die datenbank', function () {
    $user    = User::factory()->withoutTwoFactor()->create();
    $columns = DB::getSchemaBuilder()->getColumnListing('users');
    $row     = (array) DB::table('users')->where('id', $user->id)->first();

    $file = sys_get_temp_dir() . '/import_test_' . uniqid() . '.ndjson';
    writeNdjson($file, 'users', $columns, [$row]);

    DB::table('users')->where('id', $user->id)->delete();

    $this->artisan('backup:import', ['--file' => $file, '--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    // Dry-run must NOT have written anything
    expect(DB::table('users')->where('id', $user->id)->exists())->toBeFalse();

    unlink($file);
});

test('backup:import überspringt tabellen per --tables-filter', function () {
    $user    = User::factory()->withoutTwoFactor()->create();
    $columns = DB::getSchemaBuilder()->getColumnListing('users');
    $row     = (array) DB::table('users')->where('id', $user->id)->first();

    $file = sys_get_temp_dir() . '/import_test_' . uniqid() . '.ndjson';
    writeNdjson($file, 'users', $columns, [$row]);

    DB::table('users')->where('id', $user->id)->delete();

    // Import with a filter that excludes 'users'
    $this->artisan('backup:import', ['--file' => $file, '--tables' => 'workspaces'])
        ->assertSuccessful();

    // Must NOT have imported users because of the filter
    expect(DB::table('users')->where('id', $user->id)->exists())->toBeFalse();

    unlink($file);
});

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackupExport extends Command
{
    protected $signature = 'backup:export
        {--tables= : Comma-separated list of tables (default: all critical tables)}
        {--out-dir= : Output directory (default: storage/app/backups)}';

    protected $description = 'Exports critical user data to a versioned NDJSON file.';

    /** Backup order matters — FK dependencies must be satisfied top-to-bottom. */
    private const DEFAULT_TABLES = [
        'users',
        'workspaces',
        'workspace_users',
        'credit_transactions',
        'projekte',
    ];

    public function handle(): int
    {
        $tables = $this->resolveTables();
        $outDir = $this->resolveOutDir();

        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true)) {
            $this->error("Cannot create output directory: {$outDir}");
            return self::FAILURE;
        }

        $now       = Carbon::now('UTC');
        $stamp     = $now->format('Y-m-d_His');
        $fileName  = "backup_{$stamp}.ndjson";
        $filePath  = rtrim($outDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            $this->error("Cannot write to: {$filePath}");
            return self::FAILURE;
        }

        $totalRows = 0;

        foreach ($tables as $table) {
            if (! $this->tableExists($table)) {
                $this->warn("Table '{$table}' does not exist — skipping.");
                continue;
            }

            $columns = $this->getColumns($table);
            if ($columns === []) {
                $this->warn("Table '{$table}' has no readable columns — skipping.");
                continue;
            }

            // Write metadata header for this table
            fwrite($handle, json_encode([
                '__meta' => [
                    'schema_version' => 1,
                    'exported_at'    => $now->toIso8601String(),
                    'table'          => $table,
                    'columns'        => $columns,
                ],
            ], JSON_UNESCAPED_UNICODE) . "\n");

            // Stream rows to avoid loading entire table into memory
            $count = 0;
            DB::table($table)->orderBy($this->pkColumn($table))->chunk(500, function ($rows) use ($handle, &$count) {
                foreach ($rows as $row) {
                    fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_UNICODE) . "\n");
                    $count++;
                }
            });

            $totalRows += $count;
            $this->line("  {$table}: {$count} rows");
        }

        fclose($handle);

        $sizeKb = (int) round(filesize($filePath) / 1024);
        $this->info("Backup written to: {$filePath} ({$sizeKb} KB, {$totalRows} total rows)");

        return self::SUCCESS;
    }

    /** @return string[] */
    private function resolveTables(): array
    {
        $opt = $this->option('tables');
        if ($opt !== null && $opt !== '') {
            return array_values(array_filter(array_map('trim', explode(',', (string) $opt))));
        }

        return self::DEFAULT_TABLES;
    }

    private function resolveOutDir(): string
    {
        $opt = $this->option('out-dir');
        if ($opt !== null && $opt !== '') {
            return (string) $opt;
        }

        return storage_path('app/backups');
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return string[] */
    private function getColumns(string $table): array
    {
        try {
            return DB::getSchemaBuilder()->getColumnListing($table);
        } catch (\Throwable) {
            return [];
        }
    }

    private function pkColumn(string $table): string
    {
        // users uses integer id; all others use uuid id
        return 'id';
    }
}

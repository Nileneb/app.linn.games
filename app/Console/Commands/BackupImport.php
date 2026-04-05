<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupImport extends Command
{
    protected $signature = 'backup:import
        {--file= : Path to the NDJSON backup file}
        {--tables= : Only import these tables (comma-separated)}
        {--dry-run : Show what would be imported without writing anything}';

    protected $description = 'Imports a backup NDJSON file with schema-tolerant column mapping.';

    public function handle(): int
    {
        $filePath = (string) ($this->option('file') ?? '');
        if ($filePath === '' || ! is_file($filePath)) {
            $this->error('Missing or invalid --file=...');
            return self::FAILURE;
        }

        $onlyTables = $this->resolveTables();
        $dryRun     = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN mode — nothing will be written.');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error("Cannot open file: {$filePath}");
            return self::FAILURE;
        }

        $currentTable   = null;
        $exportedColumns = [];
        $currentColumns = [];   // columns that exist in current DB schema
        $insertColumns  = [];   // intersection: exported ∩ current
        $lineNr         = 0;
        $stats          = [];   // table => ['inserted' => int, 'skipped' => int, 'errors' => int]

        while (($line = fgets($handle)) !== false) {
            $lineNr++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                $this->warn("Line {$lineNr}: invalid JSON, skipping.");
                continue;
            }

            // --- Metadata header line ---
            if (isset($decoded['__meta'])) {
                $meta = $decoded['__meta'];
                $currentTable    = (string) ($meta['table'] ?? '');
                $exportedColumns = (array) ($meta['columns'] ?? []);

                if ($currentTable === '') {
                    $this->warn("Line {$lineNr}: __meta missing 'table', skipping section.");
                    $currentTable = null;
                    continue;
                }

                if ($onlyTables !== [] && ! in_array($currentTable, $onlyTables, true)) {
                    $this->line("  Skipping table '{$currentTable}' (not in --tables filter).");
                    $currentTable = null;
                    continue;
                }

                if (! DB::getSchemaBuilder()->hasTable($currentTable)) {
                    $this->warn("  Table '{$currentTable}' does not exist in current schema — skipping.");
                    $currentTable = null;
                    continue;
                }

                $currentColumns = DB::getSchemaBuilder()->getColumnListing($currentTable);
                $insertColumns  = array_values(array_intersect($exportedColumns, $currentColumns));
                $dropped        = array_values(array_diff($exportedColumns, $currentColumns));
                $added          = array_values(array_diff($currentColumns, $exportedColumns));

                $stats[$currentTable] = ['inserted' => 0, 'skipped' => 0, 'errors' => 0];

                $this->line("  Table '{$currentTable}':");
                $this->line("    columns to insert:  " . implode(', ', $insertColumns));
                if ($dropped !== []) {
                    $this->warn("    dropped (not in DB): " . implode(', ', $dropped));
                }
                if ($added !== []) {
                    $this->line("    new in DB (uses default): " . implode(', ', $added));
                }

                continue;
            }

            // --- Data row ---
            if ($currentTable === null) {
                continue; // table was skipped
            }

            if ($insertColumns === []) {
                $stats[$currentTable]['skipped']++;
                continue;
            }

            $row = $decoded;

            // Build the subset of columns we can actually insert
            $insertData = [];
            foreach ($insertColumns as $col) {
                $insertData[$col] = $row[$col] ?? null;
            }

            if ($dryRun) {
                $stats[$currentTable]['inserted']++;
                continue;
            }

            try {
                $this->upsertIgnore($currentTable, $insertData);
                $stats[$currentTable]['inserted']++;
            } catch (\Throwable $e) {
                $stats[$currentTable]['errors']++;
                $id = $row['id'] ?? '(no id)';
                $this->warn("    Error on {$currentTable} id={$id}: " . $e->getMessage());
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info('Import summary' . ($dryRun ? ' (DRY-RUN)' : '') . ':');
        foreach ($stats as $table => $s) {
            $this->line("  {$table}: {$s['inserted']} inserted, {$s['skipped']} skipped, {$s['errors']} errors");
        }

        $totalErrors = array_sum(array_column($stats, 'errors'));
        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Inserts a row, silently ignoring conflicts on the primary key.
     * Works for both integer PKs (users) and UUID PKs (all others).
     *
     * Uses raw SQL to bypass Eloquent and stay schema-tolerant.
     */
    private function upsertIgnore(string $table, array $data): void
    {
        if ($data === []) {
            return;
        }

        $cols        = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList     = implode(', ', array_map(fn (string $c) => '"' . $c . '"', $cols));
        $values      = array_values($data);

        // Cast UUID columns explicitly to avoid Postgres type errors
        $quotedTable = '"' . $table . '"';

        DB::statement(
            "INSERT INTO {$quotedTable} ({$colList}) VALUES ({$placeholders}) ON CONFLICT (id) DO NOTHING",
            $values,
        );
    }

    /** @return string[] */
    private function resolveTables(): array
    {
        $opt = $this->option('tables');
        if ($opt !== null && $opt !== '') {
            return array_values(array_filter(array_map('trim', explode(',', (string) $opt))));
        }

        return [];
    }
}

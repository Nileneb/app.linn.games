<?php

namespace App\Console\Commands;

use App\Models\Recherche\Projekt;
use Illuminate\Console\Command;

class GenerateGalaxyData extends Command
{
    protected $signature = 'galaxy:generate {projekt_id : UUID des Projekts}';

    protected $description = 'Generates galaxy-data.json for a project from pgvector embeddings via Python/UMAP';

    public function handle(): int
    {
        $projektId = $this->argument('projekt_id');

        $projekt = null;
        try {
            $projekt = Projekt::find($projektId);
        } catch (\Illuminate\Database\QueryException) {
            // invalid uuid format → treat as not found
        }

        if (! $projekt) {
            $this->error("Projekt nicht gefunden: {$projektId}");

            return self::FAILURE;
        }

        $script = config('galaxy.python_script');
        if (! file_exists($script)) {
            $this->error("Python-Script nicht gefunden: {$script}");

            return self::FAILURE;
        }

        $outDir = public_path('galaxy-data');
        $outFile = "{$outDir}/{$projekt->id}.json";

        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Output-Verzeichnis konnte nicht erstellt werden: {$outDir}");

            return self::FAILURE;
        }

        $python = config('galaxy.python_bin');

        // Set DB env vars for the Python subprocess (inherited via exec)
        putenv('DB_HOST='.config('database.connections.pgsql.host', '127.0.0.1'));
        putenv('DB_PORT='.config('database.connections.pgsql.port', 5432));
        putenv('DB_DATABASE='.config('database.connections.pgsql.database', ''));
        putenv('DB_USERNAME='.config('database.connections.pgsql.username', ''));
        putenv('DB_PASSWORD='.config('database.connections.pgsql.password', ''));

        $cmd = sprintf(
            '%s %s --projekt-id %s --out %s 2>&1',
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($projekt->id),
            escapeshellarg($outFile),
        );

        $this->info("Starte Galaxy-Generierung für Projekt {$projektId} ...");

        exec($cmd, $output, $exitCode);

        foreach ($output as $line) {
            $this->line($line);
        }

        if ($exitCode !== 0) {
            $this->error("Python-Script fehlgeschlagen (Exit Code {$exitCode}).");

            return self::FAILURE;
        }

        $this->info("Galaxy-Daten geschrieben: {$outFile}");

        return self::SUCCESS;
    }
}

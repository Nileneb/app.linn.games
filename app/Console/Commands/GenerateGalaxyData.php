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
        $outFile = "{$outDir}/{$projektId}.json";
        @mkdir($outDir, 0755, true);

        $python = config('galaxy.python_bin', 'python3');
        $envFile = base_path('.env');

        $cmd = sprintf(
            '%s %s --projekt-id %s --out %s --env %s 2>&1',
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($projektId),
            escapeshellarg($outFile),
            escapeshellarg($envFile),
        );

        $this->info("Starte Galaxy-Generierung für Projekt {$projektId} ...");
        $this->info("Command: {$cmd}");

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

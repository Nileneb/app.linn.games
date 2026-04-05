<?php

namespace App\Console\Commands\Langdock;

use App\Services\ChatTriggerwordRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GenerateFleetMapFromExport extends Command
{
    protected $signature = 'langdock:agents:generate-fleet-map
        {--export= : Path to a langdock-agent-export-*.json file}
        {--out-dir= : Output directory (default: directory of the export file)}
        {--format=both : Output format: md|json|both (default: both)}';

    protected $description = 'Generates a Fleet Map (config_key → agent_id → triggerwords) from an export JSON.';

    public function handle(): int
    {
        $exportPath = (string) ($this->option('export') ?? '');
        if ($exportPath === '') {
            $this->error('Missing --export=...');
            return self::FAILURE;
        }

        if (! is_file($exportPath)) {
            $this->error("Export file not found: {$exportPath}");
            return self::FAILURE;
        }

        $outDir = (string) ($this->option('out-dir') ?? '');
        if ($outDir === '') {
            $outDir = dirname($exportPath);
        }

        if (! is_dir($outDir)) {
            $this->error("Output directory not found: {$outDir}");
            return self::FAILURE;
        }

        $format = strtolower((string) ($this->option('format') ?? 'both'));
        if (! in_array($format, ['md', 'json', 'both'], true)) {
            $this->error('Invalid --format. Allowed: md|json|both');
            return self::FAILURE;
        }

        $raw = file_get_contents($exportPath);
        $json = json_decode((string) $raw, true);

        if (! is_array($json)) {
            $this->error('Export file is not valid JSON.');
            return self::FAILURE;
        }

        $items = $json['items'] ?? null;
        if (! is_array($items)) {
            $this->error('Export JSON missing items[].');
            return self::FAILURE;
        }

        $configKeyToTriggers = ChatTriggerwordRouter::configKeyToTriggers();

        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $configKey = (string) ($item['config_key'] ?? '');
            $agentId = (string) ($item['agent_id'] ?? '');
            $found = (bool) ($item['found'] ?? false);
            $name = (string) ($item['name'] ?? '');

            if ($configKey === '' || $agentId === '') {
                continue;
            }

            $rows[] = [
                'config_key' => $configKey,
                'agent_id' => $agentId,
                'found' => $found,
                'name' => $name,
                'triggerwords' => $configKeyToTriggers[$configKey] ?? [],
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['config_key'], $b['config_key']));

        $now = Carbon::now('UTC');
        $stamp = $now->format('Ymd-His');

        $baseName = 'langdock-fleet-map-' . $stamp;
        $mdPath = rtrim($outDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName . '.md';
        $jsonPath = rtrim($outDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName . '.json';

        $payload = [
            'generated_at' => $now->toIso8601String(),
            'source_export' => $exportPath,
            'triggerwords' => [
                'syntax' => 'Erstes Token beginnt mit @ oder #. Optional zweites Token UUID => projekt_id. Trigger wird aus dem User-Text entfernt und via context.triggerword bereitgestellt.',
                'note' => 'Wenn Trigger erkannt wird, setzt die App structured_output=true (JSON Envelope v1).',
            ],
            'items' => $rows,
        ];

        if ($format === 'json' || $format === 'both') {
            file_put_contents(
                $jsonPath,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            );
        }

        if ($format === 'md' || $format === 'both') {
            $md = [];
            $md[] = '# Langdock Fleet Map (aus Export abgeleitet)';
            $md[] = '';
            $md[] = '- generated_at: ' . $payload['generated_at'];
            $md[] = '- source_export: ' . $exportPath;
            $md[] = '';
            $md[] = '## Triggerwords';
            $md[] = '- Syntax: ' . $payload['triggerwords']['syntax'];
            $md[] = '- Hinweis: ' . $payload['triggerwords']['note'];
            $md[] = '';
            $md[] = '## Agents';
            $md[] = '| config_key | agent_id | found | name | triggerwords |';
            $md[] = '|---|---|---:|---|---|';

            foreach ($rows as $row) {
                $agentId = (string) $row['agent_id'];
                $agentId = Str::isUuid($agentId) ? $agentId : ('(invalid) ' . $agentId);
                $triggers = $row['triggerwords'] !== [] ? implode(', ', $row['triggerwords']) : '-';

                $md[] = sprintf(
                    '| %s | %s | %s | %s | %s |',
                    $row['config_key'],
                    $agentId,
                    $row['found'] ? 'yes' : 'no',
                    $this->escapeMd((string) $row['name']),
                    $this->escapeMd($triggers),
                );
            }

            $md[] = '';
            $md[] = '## Notes';
            $md[] = '- Diese Map enthält absichtlich keine Instructions/Prompts.';
            $md[] = '- Nutze die Export-JSON als editierbare Source of Truth für Instructions.';

            file_put_contents($mdPath, implode("\n", $md) . "\n");
        }

        $this->info('Generated Fleet Map:');
        if ($format === 'json' || $format === 'both') {
            $this->line('  JSON: ' . $jsonPath);
        }
        if ($format === 'md' || $format === 'both') {
            $this->line('  MD:   ' . $mdPath);
        }

        return self::SUCCESS;
    }

    private function escapeMd(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = str_replace('|', '\\|', $value);
        $value = str_replace("\n", '<br>', $value);

        return $value;
    }
}

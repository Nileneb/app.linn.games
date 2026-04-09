<?php

namespace App\Console\Commands\Langdock;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApplyInstructionPatchFromExport extends Command
{
    protected $signature = 'langdock:agents:apply-instruction-patch
        {--export= : Path to a langdock-agent-export-*.json file}
        {--config-key=* : Only patch these config keys (repeatable)}
        {--apply : Actually PATCH the agents via the update API (default is dry-run)}';

    protected $description = 'Patches agent instructions based on an export JSON (dry-run by default).';

    private const MARKER_BEGIN_V1 = '=== APP.LINN.GAMES — FLEET PATCH v1 (DO NOT REMOVE) ===';

    private const MARKER_END_V1 = '=== /APP.LINN.GAMES — FLEET PATCH v1 ===';

    private const LEGACY_MARKER_BEGIN = '=== APP: JSON ENVELOPE v1 (DO NOT REMOVE) ===';

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

        $onlyKeys = $this->option('config-key');
        $onlyKeys = is_array($onlyKeys) ? array_values(array_filter($onlyKeys)) : [];

        $apiKey = config('services.langdock.api_key');
        $getUrl = config('services.langdock.get_url');
        $updateUrl = config('services.langdock.update_url');

        if (! $apiKey || ! $getUrl || ! $updateUrl) {
            $this->error('Langdock API configuration missing (api_key/get_url/update_url).');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $patched = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $configKey = (string) ($item['config_key'] ?? '');
            $agentId = (string) ($item['agent_id'] ?? '');
            $found = (bool) ($item['found'] ?? false);

            if ($configKey === '' || $agentId === '' || ! $found) {
                $skipped++;

                continue;
            }

            if ($onlyKeys !== [] && ! in_array($configKey, $onlyKeys, true)) {
                $skipped++;

                continue;
            }

            if (! Str::isUuid($agentId)) {
                $this->warn("Skip {$configKey}: invalid agent_id");
                $skipped++;

                continue;
            }

            $getResp = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])->timeout(20)->get($getUrl, ['agentId' => $agentId]);

            if (! $getResp->successful()) {
                $this->warn("GET failed for {$configKey} ({$agentId}): HTTP {$getResp->status()}");
                $failed++;

                continue;
            }

            $agent = $getResp->json('agent');
            if (! is_array($agent)) {
                $this->warn("GET returned unexpected shape for {$configKey} ({$agentId})");
                $failed++;

                continue;
            }

            $currentInstruction = (string) ($agent['instruction'] ?? '');
            $patchedInstruction = $this->patchInstruction($currentInstruction);

            if ($patchedInstruction === $currentInstruction) {
                $this->line("OK (no change): {$configKey} ({$agentId})");
                $skipped++;

                continue;
            }

            if (! $apply) {
                $this->line("DRY-RUN would patch: {$configKey} ({$agentId})");
                $patched++;

                continue;
            }

            $patchResp = $this->patchInstructionViaApi($apiKey, $updateUrl, $agentId, $patchedInstruction);

            if ($patchResp === null) {
                $this->warn("PATCH failed for {$configKey} ({$agentId})");
                $failed++;

                continue;
            }

            $this->info("PATCHED: {$configKey} ({$agentId})");
            $patched++;
        }

        $this->newLine();
        $this->line('Summary:');
        $this->line('  patched: '.$patched);
        $this->line('  skipped: '.$skipped);
        $this->line('  failed:  '.$failed);
        $this->line('  mode:    '.($apply ? 'APPLY' : 'DRY-RUN'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Langdock deployments differ in whether the update API expects `instructions` or `instruction`.
     * We try `instruction` first, because some deployments accept `instructions` but ignore it while still returning HTTP 2xx.
     * We treat “2xx but instruction unchanged” as a failure and try the other key.
     */
    private function patchInstructionViaApi(string $apiKey, string $updateUrl, string $agentId, string $instruction): ?\Illuminate\Http\Client\Response
    {
        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ];

        $preferred = Http::withHeaders($headers)->timeout(30)->patch($updateUrl, [
            'agentId' => $agentId,
            'instruction' => $instruction,
        ]);

        if ($preferred->successful()) {
            $returned = (string) ($preferred->json('agent.instruction') ?? '');
            if ($returned === $instruction) {
                return $preferred;
            }
        }

        $fallback = Http::withHeaders($headers)->timeout(30)->patch($updateUrl, [
            'agentId' => $agentId,
            'instructions' => $instruction,
        ]);

        if ($fallback->successful()) {
            $returned = (string) ($fallback->json('agent.instruction') ?? '');
            if ($returned === $instruction) {
                return $fallback;
            }
        }

        return null;
    }

    private function patchInstruction(string $instruction): string
    {
        if (
            str_contains($instruction, self::MARKER_BEGIN_V1)
            || str_contains($instruction, self::LEGACY_MARKER_BEGIN)
        ) {
            return $instruction;
        }

        $block = implode("\n", [
            '',
            self::MARKER_BEGIN_V1,
            '- DB-first + RLS bootstrap Pflicht (SET LOCAL … als erste Anweisung in jedem execute_sql Block).',
            '- Structured output: nur wenn im System-Kontext structured_output=true steht.',
            '- JSON Envelope v1: exakt EIN gültiges JSON-Objekt, keine Markdown-Fences, keine Extra-Top-Level Keys.',
            '- Pflicht-Keys: meta, db, result, next, warnings. Wenn Daten fehlen: warnings befüllen, nichts erfinden.',
            '- Persistenz: wenn Phase-Schema mitgeliefert ist, Ergebnisse via execute_sql in die Phasentabellen schreiben (gen_random_uuid() für id).',
            self::MARKER_END_V1,
        ]);

        return rtrim($instruction)."\n".$block."\n";
    }
}

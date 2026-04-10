<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ClaudeCliService
{
    /**
     * Ruft den Main Agent via Claude CLI subprocess auf.
     *
     * @param  array<string, mixed>  $context  projekt_id, workspace_id, phase_nr, user_id, ...
     * @return array{content: string}
     *
     * @throws ClaudeCliException
     */
    public function call(string $userMessage, array $context = []): array
    {
        $systemSuffix = $this->buildContextBlock($context);

        $parts = array_filter([
            'claude',
            '--print',
            '--output-format', 'json',
            $systemSuffix !== '' ? '--append-system-prompt' : null,
            $systemSuffix !== '' ? escapeshellarg($systemSuffix) : null,
            escapeshellarg($userMessage),
        ]);

        $command = implode(' ', array_values($parts));

        $env = [];
        $apiKey = config('services.anthropic.api_key');
        if ($apiKey) {
            $env['ANTHROPIC_API_KEY'] = $apiKey;
        }

        $result = Process::timeout(120)->env($env)->run($command);

        if (! $result->successful()) {
            Log::error('Claude CLI subprocess fehlgeschlagen', [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
                'context' => $context,
            ]);

            throw new ClaudeCliException(
                'Claude CLI fehlgeschlagen (Exit '.$result->exitCode().'): '.$result->errorOutput()
            );
        }

        $decoded = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ($decoded['is_error'] ?? false)) {
            throw new ClaudeCliException('Claude CLI: ungültiger JSON-Output: '.$result->output());
        }

        return [
            'content' => $decoded['result'] ?? '',
        ];
    }

    private function buildContextBlock(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $lines = ['## Kontext'];
        foreach ($context as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = "- **{$key}:** {$value}";
            }
        }

        return implode("\n", $lines);
    }
}

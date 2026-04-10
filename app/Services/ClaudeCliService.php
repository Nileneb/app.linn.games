<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ClaudeCliService
{
    /**
     * Gibt die Production-Hardening-Flags zurück.
     * --bare: kein Hook-Loading, kein LSP, keine Auto-Discovery, kein CLAUDE.md
     * --disallowedTools: explizite Tool-Blacklist (kein Bash, kein Edit, kein Write)
     *
     * @return string[]
     */
    private function productionFlags(): array
    {
        if (app()->environment('local', 'testing')) {
            return [];
        }

        return [
            '--bare',
            '--disallowedTools', 'Bash,Edit,Write,NotebookEdit',
        ];
    }

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
            ...$this->productionFlags(),
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

    /**
     * Ruft einen Phase-Agent via Claude CLI auf.
     *
     * Nutzt --model für Worker-Modell-Auswahl und --append-system-prompt
     * für System-Prompt (PromptLoaderService + ClaudeContextBuilder).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, cost_usd: float, input_tokens: int, output_tokens: int}
     *
     * @throws ClaudeCliException
     */
    public function callForPhase(
        string $agentConfigKey,
        array $messages,
        array $context = [],
        int $timeout = 300,
    ): array {
        $model = config("services.anthropic.agent_models.{$agentConfigKey}")
            ?? config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        $systemPrompt = $this->buildPhaseSystemPrompt($agentConfigKey, $context);

        $userMessage = collect($messages)
            ->where('role', 'user')
            ->last()['content'] ?? '';

        $parts = array_filter([
            'claude',
            '--print',
            '--output-format', 'json',
            '--model', escapeshellarg($model),
            ...$this->productionFlags(),
            $systemPrompt !== '' ? '--append-system-prompt' : null,
            $systemPrompt !== '' ? escapeshellarg($systemPrompt) : null,
            escapeshellarg($userMessage),
        ]);

        $command = implode(' ', array_values($parts));

        $env = [];
        $apiKey = config('services.anthropic.api_key');
        if ($apiKey) {
            $env['ANTHROPIC_API_KEY'] = $apiKey;
        }

        $result = Process::timeout($timeout)->env($env)->run($command);

        if (! $result->successful()) {
            Log::error('Claude CLI phase agent fehlgeschlagen', [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
                'agent_config_key' => $agentConfigKey,
                'context' => $context,
            ]);

            throw new ClaudeCliException(
                'Claude CLI fehlgeschlagen (Exit '.$result->exitCode().'): '.$result->errorOutput()
            );
        }

        $decoded = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ($decoded['is_error'] ?? false)) {
            throw new ClaudeCliException('Claude CLI: ungültiger JSON-Output: '.mb_substr($result->output(), 0, 500));
        }

        return [
            'content' => $decoded['result'] ?? '',
            'cost_usd' => (float) ($decoded['total_cost_usd'] ?? 0),
            'input_tokens' => (int) ($decoded['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($decoded['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * Baut den System-Prompt für einen Phase-Agent.
     * Kombiniert PromptLoaderService (Agent-Prompt + Skills) mit ClaudeContextBuilder (DB-Kontext).
     */
    private function buildPhaseSystemPrompt(string $agentConfigKey, array $context): string
    {
        $promptFile = config("services.anthropic.agents.{$agentConfigKey}", '');

        if ($promptFile === '') {
            return $this->buildContextBlock($context);
        }

        $parts = [];

        try {
            $systemPrompt = app(PromptLoaderService::class)->buildSystemPrompt($promptFile);
            $parts[] = $systemPrompt;
        } catch (\Throwable $e) {
            Log::warning('ClaudeCliService: PromptLoaderService fehlgeschlagen', [
                'agent_config_key' => $agentConfigKey,
                'prompt_file' => $promptFile,
                'error' => $e->getMessage(),
            ]);
        }

        if (! empty($context)) {
            $contextBlock = app(ClaudeContextBuilder::class)->build($context);
            if ($contextBlock !== '') {
                $parts[] = $contextBlock;
            }
        }

        return implode("\n\n---\n\n", $parts);
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

<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly PromptLoaderService $promptLoader,
        private readonly ClaudeContextBuilder $contextBuilder,
        private readonly CreditService $creditService,
        private readonly MayringMcpClient $mayringClient,
    ) {}

    /**
     * Ruft einen konfigurierten Claude-Agent synchron auf.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, raw: array, tokens_used: int}
     *
     * @throws ClaudeAgentException
     */
    public function callByConfigKey(string $configKey, array $messages, array $context = [], int $maxTokens = 0): array
    {
        $promptFile = config("services.anthropic.agents.{$configKey}");

        if (! $promptFile) {
            throw new ClaudeAgentException("Claude Agent '{$configKey}' nicht in config/services.anthropic.agents konfiguriert.");
        }

        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new ClaudeAgentException('CLAUDE_API_KEY nicht konfiguriert.');
        }

        $systemPrompt = $this->promptLoader->buildSystemPrompt($promptFile);
        $contextBlock = $this->contextBuilder->build(array_merge(['config_key' => $configKey], $context));

        if ($contextBlock !== '') {
            $systemPrompt .= "\n\n".$contextBlock;
        }

        $model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $maxTok = $maxTokens > 0 ? $maxTokens : (int) config('services.anthropic.max_tokens', 8192);
        $workspace = $this->resolveWorkspace($context);

        if ($workspace !== null) {
            $this->creditService->assertHasBalance($workspace);
        }

        $startedAt = microtime(true);

        $response = $this->callWithRetry($apiKey, $model, $systemPrompt, $messages, $maxTok, $configKey);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::error('Claude API Fehler', [
                'config_key' => $configKey,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ClaudeAgentException("Claude API Fehler {$response->status()}: ".$response->body());
        }

        $raw = $response->json() ?? [];
        $content = $raw['content'][0]['text'] ?? '';
        $inputTokens = (int) ($raw['usage']['input_tokens'] ?? 0);
        $outputTokens = (int) ($raw['usage']['output_tokens'] ?? 0);
        $tokensUsed = $inputTokens + $outputTokens;

        if ($workspace !== null && $tokensUsed > 0) {
            $this->creditService->deduct($workspace, $inputTokens, $configKey, $outputTokens);
        }

        Log::info('Claude agent request succeeded', [
            'config_key' => $configKey,
            'model' => $model,
            'duration_ms' => $durationMs,
            'tokens_used' => $tokensUsed,
        ]);

        return [
            'content' => $content,
            'raw' => $raw,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Führt den HTTP-Request mit exponentiellem Backoff durch.
     */
    private function callWithRetry(
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens,
        string $configKey,
    ): \Illuminate\Http\Client\Response {
        $attempts = (int) config('services.anthropic.retry_attempts', 3);
        $sleepMs = (int) config('services.anthropic.retry_sleep_ms', 500);

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $lastException = null;
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->timeout(120)->post(self::API_URL, $body);

                if ($response->successful() || $response->status() < 500) {
                    return $response;
                }

                Log::warning("Claude API {$response->status()} — Retry {$attempt}/{$attempts}", [
                    'config_key' => $configKey,
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                Log::warning("Claude API ConnectionException — Retry {$attempt}/{$attempts}", [
                    'config_key' => $configKey,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < $attempts) {
                usleep($sleepMs * (2 ** ($attempt - 1)) * 1000);
            }
        }

        if ($response !== null) {
            return $response;
        }

        throw new ClaudeAgentException('Claude API nicht erreichbar: '.($lastException?->getMessage() ?? 'Unbekannter Fehler'));
    }

    private function resolveWorkspace(array $context): ?Workspace
    {
        $workspaceId = $context['workspace_id'] ?? null;

        if (! $workspaceId) {
            return null;
        }

        return Workspace::find($workspaceId);
    }
}

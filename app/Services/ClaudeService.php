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

        // Mayring-Agent erhält Tool-Use-Zugriff auf MayringCoder-MCP
        if ($configKey === 'mayring_agent') {
            return $this->callWithToolUse($apiKey, $model, $systemPrompt, $messages, $maxTok, $configKey, $workspace);
        }

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

    /**
     * Tool-Use-Loop für den Mayring-Agent.
     * Claude ruft Tools auf → MayringMcpClient führt sie aus → Ergebnis zurück an Claude.
     *
     * @return array{content: string, raw: array, tokens_used: int}
     */
    private function callWithToolUse(
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens,
        string $configKey,
        ?Workspace $workspace,
    ): array {
        $tools = [
            [
                'name' => 'search_documents',
                'description' => 'Semantische Suche über Dokument-Chunks mit optionalem Mayring-Kategorie-Filter',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Suchanfrage in natürlicher Sprache'],
                        'categories' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Mayring-Kategorien als Filter'],
                        'top_k' => ['type' => 'integer', 'default' => 8],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'ingest_and_categorize',
                'description' => 'Inhalt in MayringCoder ingesten und qualitative Kategorisierung via Ollama ausführen',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => 'Text-Inhalt der kategorisiert werden soll'],
                        'source_id' => ['type' => 'string', 'description' => 'Eindeutige ID der Quelle'],
                    ],
                    'required' => ['content', 'source_id'],
                ],
            ],
        ];

        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $maxIterations = 10;
        $iteration = 0;
        $currentMessages = $messages;
        $raw = [];
        $contentBlocks = [];

        do {
            $body = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => $currentMessages,
                'tools' => $tools,
            ];

            // Kein Retry hier — Tool-Use-Loop ist stateful; Transient-Error wirft ClaudeAgentException
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post(self::API_URL, $body);

            if ($response->failed()) {
                throw new ClaudeAgentException("Claude API Tool-Use Fehler {$response->status()}: ".$response->body());
            }

            $raw = $response->json() ?? [];
            $stopReason = $raw['stop_reason'] ?? 'end_turn';
            $totalInputTokens += (int) ($raw['usage']['input_tokens'] ?? 0);
            $totalOutputTokens += (int) ($raw['usage']['output_tokens'] ?? 0);
            $contentBlocks = $raw['content'] ?? [];

            if ($stopReason !== 'tool_use') {
                break;
            }

            // Tool-Calls ausführen
            $currentMessages[] = ['role' => 'assistant', 'content' => $contentBlocks];
            $toolResults = [];

            foreach ($contentBlocks as $block) {
                if (($block['type'] ?? '') !== 'tool_use') {
                    continue;
                }

                $toolName = $block['name'];
                $toolInput = $block['input'] ?? [];
                $toolId = $block['id'];

                try {
                    $result = match ($toolName) {
                        'search_documents' => $this->mayringClient->searchDocuments(
                            $toolInput['query'],
                            $toolInput['categories'] ?? [],
                            (int) ($toolInput['top_k'] ?? 8),
                        ),
                        'ingest_and_categorize' => $this->mayringClient->ingestAndCategorize(
                            $toolInput['content'],
                            $toolInput['source_id'],
                        ),
                        default => throw new \RuntimeException("Unbekanntes Tool: {$toolName}"),
                    };
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolId,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                } catch (\Throwable $e) {
                    Log::error('MayringCoder Tool-Use Fehler', ['tool' => $toolName, 'error' => $e->getMessage()]);
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolId,
                        'content' => 'Fehler: '.$e->getMessage(),
                        'is_error' => true,
                    ];
                }
            }

            $currentMessages[] = ['role' => 'user', 'content' => $toolResults];
            $iteration++;

            if ($iteration >= $maxIterations) {
                throw new ClaudeAgentException("Mayring Tool-Use Loop nach {$maxIterations} Iterationen abgebrochen — kein end_turn erreicht.");
            }
        } while (true);

        $tokensUsed = $totalInputTokens + $totalOutputTokens;
        $textContent = collect($contentBlocks)->firstWhere('type', 'text')['text'] ?? '';

        if ($workspace !== null && $tokensUsed > 0) {
            $this->creditService->deduct($workspace, $totalInputTokens, $configKey, $totalOutputTokens);
        }

        Log::info('Claude mayring agent request succeeded', [
            'config_key' => $configKey,
            'model' => $model,
            'iterations' => $iteration,
            'tokens_used' => $tokensUsed,
        ]);

        return [
            'content' => $textContent,
            'raw' => $raw,
            'tokens_used' => $tokensUsed,
        ];
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

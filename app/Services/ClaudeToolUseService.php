<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeToolUseService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly MayringMcpClient $mayringClient,
        private readonly CreditService $creditService,
    ) {}

    /**
     * Tool-Use-Loop für den Mayring-Agent.
     * Claude ruft Tools auf → MayringMcpClient führt sie aus → Ergebnis zurück an Claude.
     *
     * @return array{content: string, raw: array, tokens_used: int}
     */
    public function callWithToolUse(
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
                // System-Prompt + Tool-Definitionen werden gecacht.
                // Im Tool-Use-Loop wird der System-Prompt nach Iteration 1 als cache_read verrechnet.
                'system' => [['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]],
                'messages' => $currentMessages,
                'tools' => $tools,
            ];

            // 429-Retry ist hier sicher: Nachricht wird erst NACH erfolgreicher Response angehängt.
            // Transiente 5xx nach Retries werfen ClaudeAgentException.
            $maxApiRetries = 3;
            $response = null;

            for ($apiAttempt = 1; $apiAttempt <= $maxApiRetries; $apiAttempt++) {
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta' => 'prompt-caching-2024-07-31',
                    'content-type' => 'application/json',
                ])->timeout(120)->post(self::API_URL, $body);

                if ($response->successful()) {
                    break;
                }

                if ($response->status() === 429 && $apiAttempt < $maxApiRetries) {
                    $retryAfter = max(5, (int) $response->header('Retry-After', 30));
                    Log::warning("callWithToolUse 429 — backing off {$retryAfter}s (attempt {$apiAttempt}/{$maxApiRetries}, iteration {$iteration})", [
                        'config_key' => $configKey,
                    ]);
                    sleep($retryAfter);
                    continue;
                }

                break;
            }

            if ($response === null || $response->failed()) {
                throw new ClaudeAgentException("Claude API Tool-Use Fehler {$response?->status()}: ".($response?->body() ?? 'no response'));
            }

            $raw = $response->json() ?? [];
            $stopReason = $raw['stop_reason'] ?? 'end_turn';
            $totalInputTokens += (int) ($raw['usage']['input_tokens'] ?? 0);
            $totalOutputTokens += (int) ($raw['usage']['output_tokens'] ?? 0);
            $contentBlocks = $raw['content'] ?? [];

            if ($stopReason !== 'tool_use') {
                break;
            }

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
}

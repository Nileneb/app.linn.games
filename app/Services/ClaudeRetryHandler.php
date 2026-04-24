<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeRetryHandler
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * Führt den HTTP-Request mit exponentiellem Backoff durch.
     */
    public function callWithRetry(
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens,
        string $configKey,
    ): Response {
        $attempts = (int) config('services.anthropic.retry_attempts', 3);
        $sleepMs = (int) config('services.anthropic.retry_sleep_ms', 500);

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            // cache_control: system prompt in Anthropic prompt-cache schreiben.
            // Wiederholte Calls (Retry, Tool-Use-Folgeschritte) zahlen nur cache_read ($0.08/M)
            // statt cache_write ($1.00/M). Beta-Header wird unten gesetzt.
            'system' => [['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]],
            'messages' => $messages,
        ];

        $lastException = null;
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta' => 'prompt-caching-2024-07-31',
                    'content-type' => 'application/json',
                ])->timeout(120)->post(self::API_URL, $body);

                if ($response->successful()) {
                    return $response;
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After', (int) ceil($sleepMs / 1000));
                    Log::warning("Claude API 429 — backing off {$retryAfter}s (attempt {$attempt}/{$attempts})", [
                        'config_key' => $configKey,
                    ]);
                    usleep($retryAfter * 1_000_000);
                    continue;
                }

                if ($response->status() < 500) {
                    return $response;
                }

                Log::warning("Claude API {$response->status()} — Retry {$attempt}/{$attempts}", [
                    'config_key' => $configKey,
                ]);
            } catch (ConnectionException $e) {
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
}

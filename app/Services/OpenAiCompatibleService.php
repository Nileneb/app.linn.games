<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal adapter for OpenAI-compatible chat endpoints (Ollama, OpenRouter, LM Studio, ...).
 * Used when a user configures their own `llm_provider_type = openai-compatible`.
 *
 * Kein Workspace-Credit-Abzug: User zahlt selbst beim jeweiligen Anbieter.
 */
class OpenAiCompatibleService
{
    public function chat(string $endpoint, ?string $apiKey, string $model, string $systemPrompt, string $userMessage, int $timeout = 120): array
    {
        $url = $endpoint.'/v1/chat/completions';

        $headers = ['Content-Type' => 'application/json'];
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $body = [
            'model' => $model,
            'messages' => array_values(array_filter([
                $systemPrompt !== '' ? ['role' => 'system', 'content' => $systemPrompt] : null,
                ['role' => 'user', 'content' => $userMessage],
            ])),
            'stream' => false,
        ];

        $response = Http::withHeaders($headers)->timeout($timeout)->post($url, $body);

        if ($response->failed()) {
            throw new RuntimeException(
                "OpenAI-compatible endpoint returned {$response->status()}: ".$response->body()
            );
        }

        $payload = $response->json();
        $content = $payload['choices'][0]['message']['content'] ?? '';

        return [
            'content' => (string) $content,
        ];
    }
}

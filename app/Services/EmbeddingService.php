<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for generating and processing embeddings via Ollama.
 *
 * Centralizes embedding generation and validation logic to avoid duplication
 * across PaperRagController and IngestPaperJob.
 */
class EmbeddingService
{
    private const OLLAMA_MODEL = 'nomic-embed-text';

    private const OLLAMA_TIMEOUT = 30;

    /**
     * Generate an embedding for the given text via Ollama.
     *
     * @param  string  $text  The text to embed
     * @return array The validated embedding vector as array of floats
     *
     * @throws RuntimeException If Ollama request fails or returns invalid format
     */
    public function generate(string $text): array
    {
        $response = Http::timeout(self::OLLAMA_TIMEOUT)->post(
            config('services.ollama.url').'/api/embeddings',
            [
                'model' => self::OLLAMA_MODEL,
                'prompt' => $text,
            ]
        );

        if ($response->failed()) {
            $statusCode = $response->status();
            Log::error('Ollama embedding request failed', [
                'status' => $statusCode,
                'body' => $response->body(),
                'text' => substr($text, 0, 100), // Log first 100 chars only
            ]);
            throw new RuntimeException("Ollama returned {$statusCode}");
        }

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || empty($embedding)) {
            Log::warning('Ollama returned invalid embedding format', [
                'embedding' => $embedding,
                'text' => substr($text, 0, 100),
            ]);
            throw new RuntimeException('Invalid embedding format from service (expected non-empty array)');
        }

        return $this->validate($embedding);
    }

    /**
     * Validate and normalize an embedding vector.
     *
     * Safely casts values to float, filtering out nulls and non-finite numbers.
     *
     * @param  array  $embedding  The raw embedding array
     * @return array Validated embedding (all floats, no nulls)
     *
     * @throws RuntimeException If validation removes all values
     */
    public function validate(array $embedding): array
    {
        $originalCount = count($embedding);

        // Safely cast to float, filtering non-finite values
        $validated = array_map(function ($value): ?float {
            $float = (float) $value;

            return is_finite($float) ? $float : null;
        }, $embedding);

        $validated = array_filter($validated, static fn ($v) => $v !== null);

        if (empty($validated)) {
            Log::warning('Embedding validation removed all values', [
                'original_count' => $originalCount,
                'filtered_count' => count($validated),
            ]);
            throw new RuntimeException('No valid embedding values after filtering (all null or non-finite)');
        }

        return $validated;
    }

    /**
     * Convert embedding array to PostgreSQL vector literal format.
     *
     * @param  array  $embedding  Validated embedding where all values are floats
     * @return string PostgreSQL vector literal: '[0.1,0.2,...]'
     */
    public function toLiteral(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }
}

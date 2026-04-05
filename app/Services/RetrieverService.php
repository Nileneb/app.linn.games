<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retrieves the top-N most semantically similar document chunks
 * from the paper_embeddings table for a given query.
 *
 * Used as an optional pre-step in ProcessPhaseAgentJob to focus the agent
 * on relevant evidence instead of relying solely on its context window.
 */
class RetrieverService
{
    public function __construct(private readonly EmbeddingService $embeddingService) {}

    /**
     * Retrieve the top-N chunks most similar to $query for a given projekt.
     *
     * Returns an empty array when:
     * - No paper_embeddings exist for the projekt
     * - Ollama is unavailable (fails gracefully, logs warning)
     *
     * @return list<object{paper_id: string, title: string, chunk_index: int, text_chunk: string, similarity: float}>
     */
    public function retrieve(string $query, string $projektId, ?int $topN = null): array
    {
        $topN ??= (int) config('services.retriever.top_n_chunks', 5);

        $count = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM paper_embeddings WHERE projekt_id = ?',
            [$projektId]
        );

        if ((int) ($count->cnt ?? 0) === 0) {
            return [];
        }

        try {
            $embedding     = $this->embeddingService->generate($query);
            $vectorLiteral = $this->embeddingService->toLiteral($embedding);

            return DB::select(
                'SELECT paper_id, title, chunk_index, text_chunk,
                        1 - (embedding <=> ?::vector) AS similarity
                 FROM paper_embeddings
                 WHERE projekt_id = ?::uuid
                 ORDER BY embedding <=> ?::vector
                 LIMIT ?',
                [$vectorLiteral, $projektId, $vectorLiteral, $topN]
            );
        } catch (\Throwable $e) {
            Log::warning('RetrieverService: Abruf fehlgeschlagen, fahre ohne Retriever fort', [
                'projekt_id' => $projektId,
                'error'      => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Format retrieved chunks as a readable context block for the agent.
     *
     * @param  list<object>  $chunks  Result of retrieve()
     */
    public function formatAsContext(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $lines = ['=== RELEVANTE DOKUMENT-ABSCHNITTE (Embedding-Retriever) ===', ''];

        foreach ($chunks as $chunk) {
            $similarity = number_format((float) ($chunk->similarity ?? 0), 2);
            $lines[] = "--- {$chunk->title} | Abschnitt {$chunk->chunk_index} | Ähnlichkeit: {$similarity} ---";
            $lines[] = $chunk->text_chunk;
            $lines[] = '';
        }

        $lines[] = '=== ENDE DER DOKUMENT-ABSCHNITTE ===';

        return implode("\n", $lines);
    }
}

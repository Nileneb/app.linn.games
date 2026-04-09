<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
            $embedding = $this->embeddingService->generate($query);
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
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Retrieve top-K chunks from both paper_embeddings AND agent_result_embeddings,
     * with Redis session-cache (TTL 30 minutes) to reduce RAG latency.
     *
     * Cache-Key pattern: rag_cache:{projektId}:{userId}:{md5(query)}
     *
     * User-Isolation: every DB query is filtered by workspace_id + user_id + projekt_id.
     *
     * Returns a combined list sorted by similarity (descending). Each entry has:
     *   - source:      'paper' | 'agent_result'
     *   - chunk_text:  the text content
     *   - title:       paper title or source_file name
     *   - chunk_index: int (paper) or null (agent result)
     *   - similarity:  float 0–1
     *
     * Returns an empty array when Ollama is unavailable (fails gracefully, logs warning).
     * Errors are never written to the cache.
     *
     * @return list<object{source: string, chunk_text: string, title: string, chunk_index: int|null, similarity: float}>
     */
    public function retrieveWithAgentResults(
        string $query,
        string $projektId,
        string $workspaceId,
        string $userId,
        int $topK = 10
    ): array {
        $cacheKey = 'rag_cache:'.$projektId.':'.$userId.':'.md5($query);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('RetrieverService: Cache-Hit', ['cache_key' => $cacheKey]);

            return $cached;
        }

        try {
            $embedding = $this->embeddingService->generate($query);
            $vectorLiteral = $this->embeddingService->toLiteral($embedding);

            // Suche in paper_embeddings (kein User-Filter, da Papers workspace-weit geteilt werden)
            $paperChunks = DB::select(
                "SELECT paper_id,
                        title,
                        chunk_index,
                        text_chunk AS chunk_text,
                        1 - (embedding <=> ?::vector) AS similarity,
                        'paper' AS source
                 FROM paper_embeddings
                 WHERE projekt_id = ?::uuid
                 ORDER BY embedding <=> ?::vector
                 LIMIT ?",
                [$vectorLiteral, $projektId, $vectorLiteral, $topK]
            );

            // Suche in agent_result_embeddings mit vollständiger User-Isolation
            $agentChunks = DB::select(
                "SELECT NULL::uuid AS paper_id,
                        source_file AS title,
                        NULL::int AS chunk_index,
                        chunk_text,
                        1 - (embedding <=> ?::vector) AS similarity,
                        'agent_result' AS source
                 FROM agent_result_embeddings
                 WHERE workspace_id = ?::uuid
                   AND user_id = ?::uuid
                   AND projekt_id = ?::uuid
                 ORDER BY embedding <=> ?::vector
                 LIMIT ?",
                [$vectorLiteral, $workspaceId, $userId, $projektId, $vectorLiteral, $topK]
            );

            $combined = array_merge($paperChunks, $agentChunks);

            // Absteigend nach Similarity sortieren, dann auf topK kürzen
            usort($combined, static fn ($a, $b) => $b->similarity <=> $a->similarity);
            $result = array_slice($combined, 0, $topK);

            // Nur bei Erfolg cachen (Fehler werden nie gecacht)
            Cache::put($cacheKey, $result, now()->addMinutes(30));

            Log::debug('RetrieverService: Cache-Miss, Ergebnis gespeichert', [
                'cache_key' => $cacheKey,
                'paper_hits' => count($paperChunks),
                'agent_hits' => count($agentChunks),
                'combined_top' => count($result),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('RetrieverService: retrieveWithAgentResults fehlgeschlagen, fahre ohne Retriever fort', [
                'projekt_id' => $projektId,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Format retrieved chunks as a readable context block for the agent.
     *
     * Handles both shapes:
     * - Legacy retrieve():             uses text_chunk, title, chunk_index
     * - retrieveWithAgentResults():    uses chunk_text, title, chunk_index (nullable), source
     *
     * @param  list<object>  $chunks  Result of retrieve() or retrieveWithAgentResults()
     */
    public function formatAsContext(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $lines = ['=== RELEVANTE DOKUMENT-ABSCHNITTE (Embedding-Retriever) ===', ''];

        foreach ($chunks as $chunk) {
            $similarity = number_format((float) ($chunk->similarity ?? 0), 2);

            // Unterstützt beide Spaltenbezeichnungen (text_chunk legacy, chunk_text neu)
            $text = $chunk->chunk_text ?? $chunk->text_chunk ?? '';
            $title = $chunk->title ?? 'Unbekannte Quelle';
            $source = $chunk->source ?? 'paper';

            if ($source === 'agent_result') {
                $lines[] = "--- Agent-Ergebnis: {$title} | Ähnlichkeit: {$similarity} ---";
            } else {
                $chunkIndex = $chunk->chunk_index ?? '?';
                $lines[] = "--- {$title} | Abschnitt {$chunkIndex} | Ähnlichkeit: {$similarity} ---";
            }

            $lines[] = $text;
            $lines[] = '';
        }

        $lines[] = '=== ENDE DER DOKUMENT-ABSCHNITTE ===';

        return implode("\n", $lines);
    }
}

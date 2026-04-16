<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MayringMcpClient
{
    private function endpoint(): string
    {
        return rtrim((string) config('services.mayring_mcp.endpoint', 'http://localhost:8090'), '/');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.config('services.mayring_mcp.auth_token'),
            'Content-Type' => 'application/json',
        ];
    }

    private function timeout(): int
    {
        return (int) config('services.mayring_mcp.timeout', 60);
    }

    private function cacheTtl(): int
    {
        return (int) config('services.mayring_mcp.cache_ttl_seconds', 1800); // 30 min default
    }

    /**
     * Semantische Suche über Dokument-Chunks.
     * Ergebnis wird 30 Minuten gecacht — gleiche Query kostet nur beim ersten Aufruf.
     *
     * @return array{results: array, prompt_context: string}
     *
     * @throws \RuntimeException
     */
    public function searchDocuments(string $query, array $categories = [], int $topK = 8): array
    {
        $cacheKey = 'mayring_search:'.md5($query).':'.md5(implode(',', $categories)).':'.$topK;

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($query, $categories, $topK) {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->post($this->endpoint().'/search', array_filter([
                    'query' => $query,
                    'categories' => $categories ?: null,
                    'top_k' => $topK,
                ]));

            if ($response->failed()) {
                throw new \RuntimeException("MayringMcpClient: searchDocuments fehlgeschlagen ({$response->status()})");
            }

            return $response->json();
        });
    }

    /**
     * Inhalt ingesten + Mayring-Kategorisierung via Ollama.
     * Idempotent — gleiche source_id + content gibt gecachtes Ergebnis zurück (60 min TTL).
     *
     * @return array{source_id: string, chunk_ids: array, indexed: int}
     *
     * @throws \RuntimeException
     */
    public function ingestAndCategorize(string $content, string $sourceId): array
    {
        $cacheKey = 'mayring_ingest:'.md5($content).':'.$sourceId;
        $ttl = (int) config('services.mayring_mcp.ingest_cache_ttl_seconds', 3600); // 60 min

        return Cache::remember($cacheKey, $ttl, function () use ($content, $sourceId) {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->post($this->endpoint().'/ingest', [
                    'source_id'   => $sourceId,
                    'source_type' => 'agent_result',
                    'content'     => $content,
                    'categorize'  => true,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException("MayringMcpClient: ingestAndCategorize fehlgeschlagen ({$response->status()})");
            }

            $data = $response->json();

            if (empty($data) || ! isset($data['source_id'])) {
                Log::warning('MayringMcpClient: leere/ungültige Ingest-Response — wird nicht gecacht', [
                    'source_id' => $sourceId,
                    'response_keys' => array_keys($data ?? []),
                ]);
                throw new \RuntimeException('MayringMcpClient: ungültige Ingest-Response (leerer Body oder fehlende source_id)');
            }

            return $data;
        });
    }

    public function clearCacheForSource(string $sourceId): int
    {
        $prefix = config('cache.prefix', 'laravel_cache');
        $pattern = "{$prefix}:mayring_ingest:*:{$sourceId}";
        $deleted = 0;

        foreach (Redis::keys($pattern) as $key) {
            $cacheKey = str_replace("{$prefix}:", '', $key);
            Cache::forget($cacheKey);
            $deleted++;
        }

        return $deleted;
    }

    public function clearAllCache(): int
    {
        $prefix = config('cache.prefix', 'laravel_cache');
        $deleted = 0;

        foreach (['mayring_ingest:*', 'mayring_search:*'] as $pattern) {
            foreach (Redis::keys("{$prefix}:{$pattern}") as $key) {
                $cacheKey = str_replace("{$prefix}:", '', $key);
                Cache::forget($cacheKey);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Chunk by ID abrufen.
     */
    public function getChunk(string $chunkId): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout())
            ->get($this->endpoint().'/chunk/'.$chunkId);

        if ($response->failed()) {
            throw new \RuntimeException("MayringMcpClient: getChunk fehlgeschlagen ({$response->status()})");
        }

        return $response->json();
    }

    /**
     * Alle Chunks einer Quelle abrufen.
     */
    public function listBySource(string $sourceId): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout())
            ->get($this->endpoint().'/chunks', ['source_id' => $sourceId]);

        if ($response->failed()) {
            throw new \RuntimeException("MayringMcpClient: listBySource fehlgeschlagen ({$response->status()})");
        }

        return $response->json();
    }
}

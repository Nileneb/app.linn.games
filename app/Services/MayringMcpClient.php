<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
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

    private function warnOffline(string $method): void
    {
        $key = 'mayring_offline_warned';
        if (! Cache::has($key)) {
            Cache::put($key, true, 30);
            Log::warning("MayringMcpClient: offline — {$method} übersprungen", [
                'endpoint' => $this->endpoint(),
            ]);
        }
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

        try {
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
        } catch (ConnectionException) {
            $this->warnOffline('searchDocuments');

            return ['results' => [], 'prompt_context' => ''];
        }
    }

    /**
     * Inhalt ingesten + Mayring-Kategorisierung via Ollama.
     * Idempotent — gleiche source_id + content gibt gecachtes Ergebnis zurück (60 min TTL).
     *
     * @return array{source_id: string, chunk_ids: array, indexed: int}|null  null wenn MayringCoder offline
     */
    public function ingestAndCategorize(string $content, string $sourceId): ?array
    {
        $cacheKey = 'mayring_ingest:'.md5($content).':'.$sourceId;
        $ttl = (int) config('services.mayring_mcp.ingest_cache_ttl_seconds', 3600); // 60 min

        try {
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
        } catch (ConnectionException) {
            $this->warnOffline('ingestAndCategorize');

            return null;
        }
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
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->get($this->endpoint().'/chunk/'.$chunkId);

            if ($response->failed()) {
                throw new \RuntimeException("MayringMcpClient: getChunk fehlgeschlagen ({$response->status()})");
            }

            return $response->json();
        } catch (ConnectionException) {
            $this->warnOffline('getChunk');

            return [];
        }
    }

    public function listBySource(string $sourceId): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->get($this->endpoint().'/chunks', ['source_id' => $sourceId]);

            if ($response->failed()) {
                throw new \RuntimeException("MayringMcpClient: listBySource fehlgeschlagen ({$response->status()})");
            }

            return $response->json();
        } catch (ConnectionException) {
            $this->warnOffline('listBySource');

            return [];
        }
    }

    /**
     * Aggregate system stats from the public /stats/summary endpoint.
     *
     * @return array{chunks: array, sources: array, feedback: array, ingestion: array, recent_ops: array}
     */
    public function getStats(): array
    {
        try {
            $response = Http::timeout(5)->get($this->endpoint().'/stats/summary');

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (ConnectionException) {
            $this->warnOffline('getStats');

            return [];
        }
    }
}

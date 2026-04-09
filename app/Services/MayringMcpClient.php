<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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

    /**
     * Semantische Suche über Dokument-Chunks.
     *
     * @return array{results: array, prompt_context: string}
     *
     * @throws \RuntimeException
     */
    public function searchDocuments(string $query, array $categories = [], int $topK = 8): array
    {
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
    }

    /**
     * Inhalt ingesten + Mayring-Kategorisierung via Ollama.
     *
     * @return array{source_id: string, chunk_ids: array, indexed: int}
     *
     * @throws \RuntimeException
     */
    public function ingestAndCategorize(string $content, string $sourceId): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout())
            ->post($this->endpoint().'/ingest', [
                'source' => ['source_id' => $sourceId, 'source_type' => 'agent_result'],
                'content' => $content,
                'categorize' => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("MayringMcpClient: ingestAndCategorize fehlgeschlagen ({$response->status()})");
        }

        return $response->json();
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

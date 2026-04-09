<?php

namespace App\Services;

class MayringMcpClient
{
    public function searchDocuments(string $query, array $categories = [], int $topK = 8): array
    {
        return [];
    }

    public function ingestAndCategorize(string $content, string $sourceId): array
    {
        return [];
    }

    public function getChunk(string $chunkId): array
    {
        return [];
    }

    public function listBySource(string $sourceId): array
    {
        return [];
    }
}

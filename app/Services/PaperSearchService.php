<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the paper-search MCP REST endpoint directly.
 * Used by ProcessPhaseAgentJob to fetch real papers before handing context to Pi agent.
 *
 * Endpoint: POST /search on the paper-search MCP container (port 8089).
 */
class PaperSearchService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.paper_search.url', 'http://host.docker.internal:8089'), '/');
    }

    private function token(): string
    {
        return (string) config('services.paper_search.token', '');
    }

    /**
     * Search for academic papers and return a formatted context string for the Pi agent.
     *
     * @param  string[]  $sources  e.g. ['pubmed', 'arxiv', 'semantic']
     * @return array{papers: array, context: string, total: int}
     */
    public function search(
        string $query,
        array $sources = ['pubmed', 'arxiv', 'semantic'],
        int $maxResultsPerSource = 5,
        ?string $year = null,
    ): array {
        $payload = array_filter([
            'query' => $query,
            'sources' => $sources,
            'max_results_per_source' => $maxResultsPerSource,
            'year' => $year,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->token(),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->baseUrl().'/search', $payload);

            if (! $response->successful()) {
                Log::warning('PaperSearchService: search request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                    'query' => $query,
                ]);

                return ['papers' => [], 'context' => '', 'total' => 0];
            }

            $data = $response->json();
            $papers = $data['papers'] ?? [];

            return [
                'papers' => $papers,
                'context' => $this->formatAsContext($papers, $query),
                'total' => count($papers),
            ];
        } catch (\Throwable $e) {
            Log::warning('PaperSearchService: exception during search', [
                'query' => $query,
                'exception' => $e->getMessage(),
            ]);

            return ['papers' => [], 'context' => '', 'total' => 0];
        }
    }

    /**
     * Format paper list as a Markdown context block for Pi agent system prompt.
     *
     * @param  array<int, array<string, mixed>>  $papers
     */
    private function formatAsContext(array $papers, string $query): string
    {
        if (empty($papers)) {
            return '';
        }

        $lines = [];
        $lines[] = "## Gefundene Papers (Suchanfrage: \"{$query}\")";
        $lines[] = '';
        $lines[] = '| # | Titel | Autoren | Jahr | Quelle | ID |';
        $lines[] = '|---|-------|---------|------|--------|----|';

        foreach ($papers as $i => $paper) {
            $nr = $i + 1;
            $titel = mb_substr((string) ($paper['title'] ?? 'N/A'), 0, 80);
            $autoren = mb_substr((string) ($paper['authors'] ?? 'N/A'), 0, 50);
            $jahr = $paper['year'] ?? 'N/A';
            $quelle = $paper['source'] ?? 'N/A';
            $id = $paper['paper_id'] ?? $paper['id'] ?? 'N/A';
            $lines[] = "| {$nr} | {$titel} | {$autoren} | {$jahr} | {$quelle} | {$id} |";
        }

        $lines[] = '';
        $lines[] = '### Paper-Abstracts';
        $lines[] = '';

        foreach ($papers as $i => $paper) {
            $nr = $i + 1;
            $titel = $paper['title'] ?? 'N/A';
            $abstract = mb_substr((string) ($paper['abstract'] ?? 'Kein Abstract verfügbar'), 0, 500);
            $doi = $paper['doi'] ?? null;
            $lines[] = "**#{$nr} {$titel}**";
            if ($doi) {
                $lines[] = "DOI: {$doi}";
            }
            $lines[] = $abstract;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}

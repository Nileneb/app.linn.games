<?php

namespace App\Http\Controllers;

use App\Jobs\IngestPaperJob;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class PaperRagController extends Controller
{
    private const EMBEDDING_RATE_LIMIT_KEY = 'embedding_requests';
    private const EMBEDDING_RATE_LIMIT_PER_MINUTE = 30;

    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'paper_id'   => ['required', 'string', 'max:255'],
            'source'     => ['required', 'string', 'max:50'],
            'title'      => ['required', 'string'],
            'text'       => ['required', 'string'],
            'projekt_id' => ['nullable', 'uuid', 'exists:projekte,id'],
            'metadata'   => ['nullable', 'array'],
        ]);

        IngestPaperJob::dispatch(
            $data['paper_id'],
            $data['source'],
            $data['title'],
            $data['text'],
            $data['projekt_id'] ?? null,
            $data['metadata'] ?? null,
        );

        return response()->json(['status' => 'queued']);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'           => ['required', 'string'],
            'projekt_id'  => ['nullable', 'uuid'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset'      => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        // Rate-limit embedding requests
        $rateLimitKey = self::EMBEDDING_RATE_LIMIT_KEY . ':' . ($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::EMBEDDING_RATE_LIMIT_PER_MINUTE)) {
            return response()->json(
                ['error' => 'Rate limit exceeded. Maximum ' . self::EMBEDDING_RATE_LIMIT_PER_MINUTE . ' requests per minute'],
                429
            );
        }
        RateLimiter::hit($rateLimitKey, 60);

        $maxResults = (int) ($data['max_results'] ?? 5);
        $offset     = (int) ($data['offset'] ?? 0);
        $projektId  = $data['projekt_id'] ?? null;

        try {
            $embeddingService = app(EmbeddingService::class);
            
            try {
                $embedding = $embeddingService->generate($data['q']);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'error' => 'Embedding service unavailable',
                    'details' => $e->getMessage(),
                ], 503);
            }

            $vectorLiteral = $embeddingService->toLiteral($embedding);

            $rows = DB::select(
                'SELECT id, projekt_id, source, paper_id, title, chunk_index, text_chunk, metadata,
                        1 - (embedding <=> ?::vector) AS similarity
                 FROM paper_embeddings
                 WHERE (?::text IS NULL OR projekt_id = ?::uuid)
                 ORDER BY embedding <=> ?::vector
                 LIMIT ? OFFSET ?',
                [$vectorLiteral, $projektId, $projektId, $vectorLiteral, $maxResults, $offset]
            );

            return response()->json($rows);
        } catch (\Throwable $e) {
            \Log::error('PaperRagController::search error', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'query'     => $data['q'],
            ]);
            return response()->json([
                'error' => 'Search failed',
                'details' => 'An unexpected error occurred',
            ], 500);
        }
    }
}

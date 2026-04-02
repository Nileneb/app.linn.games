<?php

namespace App\Http\Controllers;

use App\Jobs\IngestPaperJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaperRagController extends Controller
{
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
        ]);

        $maxResults = $data['max_results'] ?? 5;
        $projektId  = $data['projekt_id'] ?? null;

        $embeddingResponse = Http::timeout(30)->post(config('services.ollama.url') . '/api/embeddings', [
            'model'  => 'nomic-embed-text',
            'prompt' => $data['q'],
        ]);

        if ($embeddingResponse->failed()) {
            return response()->json(['error' => 'Embedding service unavailable'], 503);
        }

        $embedding = $embeddingResponse->json('embedding');
        $vectorLiteral = '[' . implode(',', $embedding) . ']';

        $rows = DB::select(
            'SELECT id, projekt_id, source, paper_id, title, chunk_index, text_chunk, metadata,
                    1 - (embedding <=> ?::vector) AS similarity
             FROM paper_embeddings
             WHERE (? IS NULL OR projekt_id = ?::uuid)
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$vectorLiteral, $projektId, $projektId, $vectorLiteral, $maxResults]
        );

        return response()->json($rows);
    }
}

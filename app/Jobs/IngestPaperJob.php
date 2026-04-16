<?php

namespace App\Jobs;

use App\Services\EmbeddingService;
use App\Services\MayringMcpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IngestPaperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    private const CHUNK_SIZE = 500;

    private const CHUNK_OVERLAP = 100;

    public function __construct(
        public readonly string $paperId,
        public readonly string $source,
        public readonly string $title,
        public readonly string $text,
        public readonly ?string $projektId = null,
        public readonly ?array $metadata = null,
    ) {}

    public function handle(): void
    {
        $chunks = $this->chunkText($this->text);
        $embeddingService = app(EmbeddingService::class);

        DB::transaction(function () use ($chunks, $embeddingService): void {
            foreach ($chunks as $index => $chunk) {
                try {
                    $embedding = $embeddingService->generate($chunk);
                    $vectorLiteral = $embeddingService->toLiteral($embedding);

                    DB::statement(
                        'INSERT INTO paper_embeddings (id, projekt_id, source, paper_id, title, chunk_index, text_chunk, metadata, embedding)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::vector)',
                        [
                            Str::uuid()->toString(),
                            $this->projektId,
                            $this->source,
                            $this->paperId,
                            $this->title,
                            $index,
                            $chunk,
                            json_encode($this->metadata),
                            $vectorLiteral,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('Chunk processing failed, rolling back all inserts', [
                        'paper_id' => $this->paperId,
                        'chunk' => $index,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }, attempts: 3);

        Log::info('Paper ingested to local embeddings', [
            'paper_id' => $this->paperId,
            'chunk_count' => count($chunks),
        ]);

        try {
            app(MayringMcpClient::class)->ingestAndCategorize(
                $this->text,
                "paper:{$this->paperId}",
            );
            Log::info('Paper ingested to MayringCoder RAG', ['paper_id' => $this->paperId]);
        } catch (\Throwable $e) {
            Log::warning('MayringCoder RAG ingest failed (non-fatal)', [
                'paper_id' => $this->paperId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function chunkText(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $total = count($words);

        for ($start = 0; $start < $total; $start += self::CHUNK_SIZE - self::CHUNK_OVERLAP) {
            $slice = array_slice($words, $start, self::CHUNK_SIZE);
            $chunks[] = implode(' ', $slice);

            if ($start + self::CHUNK_SIZE >= $total) {
                break;
            }
        }

        return $chunks;
    }
}

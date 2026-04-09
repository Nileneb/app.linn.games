<?php

namespace App\Jobs;

use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IngestAgentResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    private const CHUNK_SIZE = 500;
    private const CHUNK_OVERLAP = 100;

    public function __construct(
        public readonly string $sourcePath,
        public readonly string $workspaceId,
        public readonly string $userId,
        public readonly string $projektId,
    ) {}

    public function handle(): void
    {
        $content = Storage::disk('local')->get($this->sourcePath);

        if ($content === null) {
            Log::error('IngestAgentResultJob: source file not found', [
                'source_path' => $this->sourcePath,
                'projekt_id'  => $this->projektId,
            ]);
            $this->fail(new \RuntimeException("Source file not found: {$this->sourcePath}"));
            return;
        }

        $chunks = $this->chunkText($content);
        $embeddingService = app(EmbeddingService::class);

        DB::transaction(function () use ($chunks, $embeddingService): void {
            // Idempotenz: bestehende Embeddings für diese source_file löschen
            DB::statement(
                'DELETE FROM agent_result_embeddings WHERE source_file = ?',
                [$this->sourcePath]
            );

            foreach ($chunks as $index => $chunk) {
                try {
                    $embedding = $embeddingService->generate($chunk);
                    $vectorLiteral = $embeddingService->toLiteral($embedding);

                    DB::statement(
                        'INSERT INTO agent_result_embeddings
                            (id, workspace_id, user_id, projekt_id, chunk_text, embedding, source_file, created_at)
                         VALUES (?, ?, ?, ?, ?, ?::vector, ?, NOW())',
                        [
                            Str::uuid()->toString(),
                            $this->workspaceId,
                            $this->userId,
                            $this->projektId,
                            $chunk,
                            $vectorLiteral,
                            $this->sourcePath,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('IngestAgentResultJob: chunk processing failed, rolling back', [
                        'source_path' => $this->sourcePath,
                        'projekt_id'  => $this->projektId,
                        'chunk_index' => $index,
                        'message'     => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }, attempts: 3);

        Log::info('IngestAgentResultJob: agent result ingested successfully', [
            'source_path' => $this->sourcePath,
            'projekt_id'  => $this->projektId,
            'chunk_count' => count($chunks),
        ]);
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

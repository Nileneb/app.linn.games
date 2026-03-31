<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        foreach ($chunks as $index => $chunk) {
            $response = Http::timeout(30)->post('http://ollama:11434/api/embeddings', [
                'model' => 'nomic-embed-text',
                'prompt' => $chunk,
            ]);

            if ($response->failed()) {
                Log::error('Ollama embedding failed', [
                    'paper_id' => $this->paperId,
                    'chunk' => $index,
                    'status' => $response->status(),
                ]);
                $this->fail(new \RuntimeException("Ollama returned {$response->status()} for chunk {$index}"));
                return;
            }

            $embedding = $response->json('embedding');
            $vectorLiteral = '[' . implode(',', $embedding) . ']';

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

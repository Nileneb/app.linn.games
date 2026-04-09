<?php

namespace App\Jobs;

use App\Actions\SendAgentMessage;
use App\Models\ChunkCodierung;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMayringChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        private readonly string $codierungId,
        private readonly string $textChunk,
        private readonly string $projektId,
    ) {}

    public function handle(): void
    {
        // Abort early if the batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $codierung = ChunkCodierung::findOrFail($this->codierungId);

        $messages = [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Codiere den folgenden Textabschnitt nach Mayring (qualitative Inhaltsanalyse).

Antworte ausschließlich im folgenden JSON-Format, ohne Kommentare oder zusätzlichen Text:
{
  "paraphrase": "...",
  "generalisierung": "...",
  "reduktion": "...",
  "kategorie": "..."
}

Textabschnitt:
{$this->textChunk}
PROMPT,
            ],
        ];

        try {
            $response = app(SendAgentMessage::class)->execute(
                'mayring_agent',
                $messages,
                120,
                ['projekt_id' => $this->projektId],
            );

            if (! $response['success']) {
                $codierung->markFailed($response['content']);
                Log::warning('ProcessMayringChunkJob: Agent-Fehler', [
                    'codierung_id' => $this->codierungId,
                    'error' => $response['content'],
                ]);

                return;
            }

            $parsed = $this->parseResponse($response['content']);
            $codierung->markCompleted($parsed);
        } catch (\Throwable $e) {
            $codierung->markFailed($e->getMessage());
            Log::error('ProcessMayringChunkJob: Exception', [
                'codierung_id' => $this->codierungId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function parseResponse(string $content): array
    {
        // Strip code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $cleaned = preg_replace('/```\s*$/m', '', $cleaned ?? $content);

        $data = json_decode(trim($cleaned ?? $content), true);

        if (! is_array($data)) {
            return [
                'paraphrase' => null,
                'generalisierung' => null,
                'reduktion' => null,
                'kategorie' => $content, // store raw as fallback
            ];
        }

        return [
            'paraphrase' => $data['paraphrase'] ?? null,
            'generalisierung' => $data['generalisierung'] ?? null,
            'reduktion' => $data['reduktion'] ?? null,
            'kategorie' => $data['kategorie'] ?? null,
        ];
    }
}

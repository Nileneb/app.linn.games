<?php

namespace App\Jobs;

use App\Models\ChunkCodierung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches one ProcessMayringChunkJob for every paper_embedding belonging
 * to the given project. Already-completed chunks are skipped (idempotent).
 */
class ProcessMayringBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly string $projektId) {}

    public function handle(): void
    {
        $embeddings = DB::select(
            'SELECT id, text_chunk FROM paper_embeddings WHERE projekt_id = ?::uuid ORDER BY paper_id, chunk_index',
            [$this->projektId]
        );

        if (empty($embeddings)) {
            Log::info('ProcessMayringBatchJob: keine Embeddings für Projekt', ['projekt_id' => $this->projektId]);

            return;
        }

        $jobs = [];
        foreach ($embeddings as $embedding) {
            // Skip if already successfully coded
            $existing = ChunkCodierung::where('paper_embedding_id', $embedding->id)
                ->where('status', 'completed')
                ->exists();

            if ($existing) {
                continue;
            }

            // Upsert a pending codierung record
            $codierung = ChunkCodierung::firstOrCreate(
                ['paper_embedding_id' => $embedding->id],
                [
                    'projekt_id' => $this->projektId,
                    'status' => 'pending',
                ],
            );

            // Reset failed ones so they retry
            if ($codierung->status === 'failed') {
                $codierung->update(['status' => 'pending', 'error_message' => null]);
            }

            $jobs[] = new ProcessMayringChunkJob(
                $codierung->id,
                $embedding->text_chunk,
                $this->projektId,
            );
        }

        if (empty($jobs)) {
            Log::info('ProcessMayringBatchJob: alle Chunks bereits codiert', ['projekt_id' => $this->projektId]);

            return;
        }

        Bus::batch($jobs)
            ->name('mayring-codierung:'.$this->projektId)
            ->allowFailures()
            ->dispatch();

        Log::info('ProcessMayringBatchJob: Batch gestartet', [
            'projekt_id' => $this->projektId,
            'chunks' => count($jobs),
        ]);
    }
}

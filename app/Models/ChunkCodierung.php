<?php

namespace App\Models;

use App\Models\Recherche\Projekt;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChunkCodierung extends Model
{
    use HasUuids;

    protected $table = 'chunk_codierungen';

    protected $fillable = [
        'projekt_id',
        'paper_embedding_id',
        'paraphrase',
        'generalisierung',
        'reduktion',
        'kategorie',
        'status',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class);
    }

    public function markCompleted(array $parsed): void
    {
        $this->update([
            'status'          => 'completed',
            'paraphrase'      => $parsed['paraphrase'] ?? null,
            'generalisierung' => $parsed['generalisierung'] ?? null,
            'reduktion'       => $parsed['reduktion'] ?? null,
            'kategorie'       => $parsed['kategorie'] ?? null,
            'error_message'   => null,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}

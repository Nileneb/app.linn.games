<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperEmbedding extends Model
{
    use HasUuids;

    protected $table = 'paper_embeddings';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'source',
        'paper_id',
        'title',
        'chunk_index',
        'text_chunk',
        'metadata',
        'erstellt_am',
    ];

    protected $casts = [
        'metadata' => 'array',
        'erstellt_am' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

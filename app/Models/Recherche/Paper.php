<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paper extends Model
{
    use HasUuids;

    protected $table = 'papers';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'source',
        'paper_id',
        'title',
        'abstract',
        'authors',
        'doi',
        'url',
        'year',
        'metadata',
    ];

    protected $casts = [
        'authors' => 'array',
        'metadata' => 'array',
        'year' => 'integer',
        'erstellt_am' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

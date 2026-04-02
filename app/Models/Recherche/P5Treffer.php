<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class P5Treffer extends Model
{
    use HasUuids;

    protected $table = 'p5_treffer';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'record_id',
        'titel',
        'autoren',
        'jahr',
        'journal',
        'doi',
        'abstract',
        'datenbank_quelle',
        'ist_duplikat',
        'duplikat_von',
        'retrieval_downloaded',
        'retrieval_source_url',
        'retrieval_storage_path',
        'retrieval_status',
        'retrieval_last_response',
        'retrieval_checked_at',
    ];

    protected $casts = [
        'jahr' => 'integer',
        'ist_duplikat' => 'boolean',
        'retrieval_downloaded' => 'boolean',
        'retrieval_checked_at' => 'datetime',
        'erstellt_am' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function duplikatOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplikat_von');
    }

    public function duplikate(): HasMany
    {
        return $this->hasMany(self::class, 'duplikat_von');
    }

    public function screeningEntscheidungen(): HasMany
    {
        return $this->hasMany(P5ScreeningEntscheidung::class, 'treffer_id');
    }

    public function qualitaetsbewertung(): HasMany
    {
        return $this->hasMany(P6Qualitaetsbewertung::class, 'treffer_id');
    }

    public function datenextraktion(): HasMany
    {
        return $this->hasMany(P7Datenextraktion::class, 'treffer_id');
    }
}

<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P3Datenbankmatrix extends Model
{
    use HasUuids;

    protected $table = 'p3_datenbankmatrix';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'datenbank',
        'disziplin',
        'abdeckung',
        'besonderheit',
        'zugang',
        'empfohlen',
        'begruendung',
    ];

    protected $casts = [
        'empfohlen' => 'boolean',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

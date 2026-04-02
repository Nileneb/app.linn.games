<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P8Suchprotokoll extends Model
{
    use HasUuids;

    protected $table = 'p8_suchprotokoll';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'suchstring_id',
        'datenbank',
        'suchdatum',
        'db_version',
        'suchstring_final',
        'gesetzte_filter',
        'treffer_gesamt',
        'treffer_eindeutig',
    ];

    protected $casts = [
        'gesetzte_filter' => 'array',
        'suchdatum' => 'date',
        'treffer_gesamt' => 'integer',
        'treffer_eindeutig' => 'integer',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function suchstring(): BelongsTo
    {
        return $this->belongsTo(P4Suchstring::class, 'suchstring_id');
    }
}

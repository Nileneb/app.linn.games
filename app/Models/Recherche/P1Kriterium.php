<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P1Kriterium extends Model
{
    use HasUuids;

    protected $table = 'p1_kriterien';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'kriterium_typ',
        'beschreibung',
        'begruendung',
        'quellbezug',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

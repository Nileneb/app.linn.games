<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P5ScreeningKriterium extends Model
{
    use HasUuids;

    protected $table = 'p5_screening_kriterien';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'level',
        'kriterium_typ',
        'beschreibung',
        'beispiel',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

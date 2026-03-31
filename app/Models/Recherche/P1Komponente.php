<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P1Komponente extends Model
{
    use HasUuids;

    protected $table = 'p1_komponenten';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'modell',
        'komponente_kuerzel',
        'komponente_label',
        'synonyme',
        'inhaltlicher_begriff_de',
        'englische_entsprechung',
        'mesh_term',
        'thesaurus_term',
        'anmerkungen',
    ];

    protected $casts = [
        'synonyme' => 'array',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

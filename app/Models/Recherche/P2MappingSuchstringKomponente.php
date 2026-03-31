<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2MappingSuchstringKomponente extends Model
{
    use HasUuids;

    protected $table = 'p2_mapping_suchstring_komponenten';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'komponente_label',
        'suchbegriffe',
        'sprache',
        'trunkierung_genutzt',
        'anmerkung',
    ];

    protected $casts = [
        'suchbegriffe' => 'array',
        'trunkierung_genutzt' => 'boolean',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

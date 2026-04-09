<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P3GeografischerFilter extends Model
{
    use HasUuids;

    protected $table = 'p3_geografische_filter';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'region_land',
        'validierter_filter_vorhanden',
        'filtername_quelle',
        'sensitivitaet_prozent',
        'hilfsstrategie',
    ];

    protected $casts = [
        'validierter_filter_vorhanden' => 'boolean',
        'sensitivitaet_prozent' => 'decimal:2',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

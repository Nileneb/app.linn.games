<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P8Limitation extends Model
{
    use HasUuids;

    protected $table = 'p8_limitationen';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'limitationstyp',
        'beschreibung',
        'auswirkung_auf_vollstaendigkeit',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

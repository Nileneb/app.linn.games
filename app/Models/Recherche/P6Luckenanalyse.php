<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P6Luckenanalyse extends Model
{
    use HasUuids;

    protected $table = 'p6_luckenanalyse';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'fehlender_aspekt',
        'fehlender_studientyp',
        'moegliche_konsequenz',
        'empfehlung',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

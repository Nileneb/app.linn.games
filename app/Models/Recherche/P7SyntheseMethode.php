<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P7SyntheseMethode extends Model
{
    use HasUuids;

    protected $table = 'p7_synthese_methode';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'methode',
        'gewaehlt',
        'begruendung',
    ];

    protected $casts = [
        'gewaehlt' => 'boolean',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

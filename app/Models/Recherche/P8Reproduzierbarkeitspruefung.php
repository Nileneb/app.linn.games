<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P8Reproduzierbarkeitspruefung extends Model
{
    use HasUuids;

    protected $table = 'p8_reproduzierbarkeitspruefung';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'pruefpunkt',
        'erfuellt',
        'anmerkung',
    ];

    protected $casts = [
        'erfuellt' => 'boolean',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

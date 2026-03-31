<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P8UpdatePlan extends Model
{
    use HasUuids;

    protected $table = 'p8_update_plan';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'update_typ',
        'intervall',
        'verantwortlich',
        'tool',
        'naechstes_update',
    ];

    protected $casts = [
        'naechstes_update' => 'date',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

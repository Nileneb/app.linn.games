<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Phase extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'phasen';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'phase_nr',
        'titel',
        'status',
        'notizen',
        'abgeschlossen_am',
    ];

    protected $casts = [
        'phase_nr' => 'integer',
        'abgeschlossen_am' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

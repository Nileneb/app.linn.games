<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P5ScreeningEntscheidung extends Model
{
    use HasUuids;

    protected $table = 'p5_screening_entscheidungen';

    public $timestamps = false;

    protected $fillable = [
        'treffer_id',
        'level',
        'entscheidung',
        'ausschlussgrund',
        'reviewer',
        'datum',
        'anmerkung',
    ];

    protected $casts = [
        'datum' => 'date',
    ];

    public function treffer(): BelongsTo
    {
        return $this->belongsTo(P5Treffer::class, 'treffer_id');
    }
}

<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P6Qualitaetsbewertung extends Model
{
    use HasUuids;

    protected $table = 'p6_qualitaetsbewertung';

    public $timestamps = false;

    protected $fillable = [
        'treffer_id',
        'studientyp',
        'rob_tool',
        'gesamturteil',
        'hauptproblem',
        'im_review_behalten',
        'anmerkung',
        'bewertet_von',
        'bewertet_am',
    ];

    protected $casts = [
        'im_review_behalten' => 'boolean',
        'bewertet_am' => 'date',
    ];

    public function treffer(): BelongsTo
    {
        return $this->belongsTo(P5Treffer::class, 'treffer_id');
    }
}

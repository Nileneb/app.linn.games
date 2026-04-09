<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P7Datenextraktion extends Model
{
    use HasUuids;

    protected $table = 'p7_datenextraktion';

    public $timestamps = false;

    protected $fillable = [
        'treffer_id',
        'land',
        'stichprobe_kontext',
        'phaenomen_intervention',
        'outcome_ergebnis',
        'hauptbefund',
        'qualitaetsurteil',
        'anmerkung',
    ];

    public function treffer(): BelongsTo
    {
        return $this->belongsTo(P5Treffer::class, 'treffer_id');
    }
}

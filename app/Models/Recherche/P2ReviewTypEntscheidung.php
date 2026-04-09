<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2ReviewTypEntscheidung extends Model
{
    use HasUuids;

    protected $table = 'p2_review_typ_entscheidung';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'review_typ',
        'passt',
        'begruendung',
    ];

    protected $casts = [
        'passt' => 'boolean',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

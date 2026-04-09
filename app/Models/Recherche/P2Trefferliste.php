<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2Trefferliste extends Model
{
    use HasUuids;

    protected $table = 'p2_trefferlisten';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'datenbank',
        'suchstring',
        'treffer_gesamt',
        'einschaetzung',
        'anpassung_notwendig',
        'suchdatum',
    ];

    protected $casts = [
        'treffer_gesamt' => 'integer',
        'anpassung_notwendig' => 'boolean',
        'suchdatum' => 'date',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

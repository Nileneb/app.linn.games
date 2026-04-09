<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P3GraueLiteratur extends Model
{
    use HasUuids;

    protected $table = 'p3_graue_literatur';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'quelle',
        'typ',
        'url',
        'suchpfad',
        'relevanz',
        'anmerkung',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

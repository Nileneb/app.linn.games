<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P3Disziplin extends Model
{
    use HasUuids;

    protected $table = 'p3_disziplinen';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'disziplin',
        'art',
        'relevanz',
        'anmerkung',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P1Warnsignal extends Model
{
    use HasUuids;

    protected $table = 'p1_warnsignale';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'lfd_nr',
        'warnsignal',
        'moegliche_auswirkung',
        'handlungsempfehlung',
    ];

    protected $casts = [
        'lfd_nr' => 'integer',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

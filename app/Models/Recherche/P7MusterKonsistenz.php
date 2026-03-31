<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P7MusterKonsistenz extends Model
{
    use HasUuids;

    protected $table = 'p7_muster_konsistenz';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'muster_befund',
        'unterstuetzende_quellen',
        'widersprechende_quellen',
        'moegliche_erklaerung',
    ];

    protected $casts = [
        'unterstuetzende_quellen' => 'array',
        'widersprechende_quellen' => 'array',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

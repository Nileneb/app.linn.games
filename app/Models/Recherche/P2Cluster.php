<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2Cluster extends Model
{
    use HasUuids;

    protected $table = 'p2_cluster';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'cluster_id',
        'cluster_label',
        'beschreibung',
        'treffer_schaetzung',
        'relevanz',
    ];

    protected $casts = [
        'treffer_schaetzung' => 'integer',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P4ThesaurusMapping extends Model
{
    use HasUuids;

    protected $table = 'p4_thesaurus_mapping';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'freitext_de',
        'freitext_en',
        'mesh_term',
        'emtree_term',
        'psycinfo_term',
        'anmerkung',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

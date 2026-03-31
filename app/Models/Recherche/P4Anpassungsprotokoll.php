<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P4Anpassungsprotokoll extends Model
{
    use HasUuids;

    protected $table = 'p4_anpassungsprotokoll';
    public $timestamps = false;

    protected $fillable = [
        'suchstring_id',
        'version',
        'datum',
        'aenderung',
        'grund',
        'treffer_vorher',
        'treffer_nachher',
        'entscheidung',
    ];

    protected $casts = [
        'datum' => 'date',
        'treffer_vorher' => 'integer',
        'treffer_nachher' => 'integer',
    ];

    public function suchstring(): BelongsTo
    {
        return $this->belongsTo(P4Suchstring::class, 'suchstring_id');
    }
}

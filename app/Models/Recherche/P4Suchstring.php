<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class P4Suchstring extends Model
{
    use HasUuids;

    protected $table = 'p4_suchstrings';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'datenbank',
        'suchstring',
        'feldeinschraenkung',
        'gesetzte_filter',
        'treffer_anzahl',
        'einschaetzung',
        'aenderungs_grund',
        'version',
        'suchdatum',
    ];

    protected $casts = [
        'gesetzte_filter' => 'array',
        'treffer_anzahl' => 'integer',
        'suchdatum' => 'date',
        'erstellt_am' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function anpassungsprotokoll(): HasMany
    {
        return $this->hasMany(P4Anpassungsprotokoll::class, 'suchstring_id');
    }

    public function suchprotokolle(): HasMany
    {
        return $this->hasMany(P8Suchprotokoll::class, 'suchstring_id');
    }
}

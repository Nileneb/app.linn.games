<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Projekt extends Model
{
    use HasUuids;

    protected $table = 'projekte';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'titel',
        'forschungsfrage',
        'review_typ',
        'verantwortlich',
        'startdatum',
        'notizen',
    ];

    protected $casts = [
        'startdatum' => 'date',
        'letztes_update' => 'datetime',
        'erstellt_am' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phasen(): HasMany
    {
        return $this->hasMany(Phase::class, 'projekt_id');
    }

    public function p1Strukturmodellwahl(): HasMany
    {
        return $this->hasMany(P1Strukturmodellwahl::class, 'projekt_id');
    }

    public function p1Komponenten(): HasMany
    {
        return $this->hasMany(P1Komponente::class, 'projekt_id');
    }

    public function p1Kriterien(): HasMany
    {
        return $this->hasMany(P1Kriterium::class, 'projekt_id');
    }

    public function p1Warnsignale(): HasMany
    {
        return $this->hasMany(P1Warnsignal::class, 'projekt_id');
    }

    public function p5Treffer(): HasMany
    {
        return $this->hasMany(P5Treffer::class, 'projekt_id');
    }
}

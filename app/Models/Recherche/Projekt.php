<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Projekt extends Model
{
    use HasFactory, HasUuids, LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['titel', 'forschungsfrage', 'review_typ'])
            ->logOnlyDirty();
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

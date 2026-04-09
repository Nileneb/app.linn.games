<?php

namespace App\Models\Recherche;

use App\Models\PhaseAgentResult;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Projekt extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'projekte';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'titel',
        'forschungsfrage',
        'review_typ',
        'verantwortlich',
        'startdatum',
        'notizen',
    ];

    protected $casts = [
        'startdatum' => 'date',
        'erstellt_am' => 'datetime',
        'letztes_update' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
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

    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class, 'projekt_id');
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

    // P2 — Review-Typ & Suchstrategie
    public function p2ReviewTypEntscheidungen(): HasMany
    {
        return $this->hasMany(P2ReviewTypEntscheidung::class, 'projekt_id');
    }

    public function p2Cluster(): HasMany
    {
        return $this->hasMany(P2Cluster::class, 'projekt_id');
    }

    public function p2MappingSuchstringKomponenten(): HasMany
    {
        return $this->hasMany(P2MappingSuchstringKomponente::class, 'projekt_id');
    }

    public function p2Trefferlisten(): HasMany
    {
        return $this->hasMany(P2Trefferliste::class, 'projekt_id');
    }

    // P3 — Quellenauswahl
    public function p3Datenbankmatrix(): HasMany
    {
        return $this->hasMany(P3Datenbankmatrix::class, 'projekt_id');
    }

    public function p3Disziplinen(): HasMany
    {
        return $this->hasMany(P3Disziplin::class, 'projekt_id');
    }

    public function p3GeografischeFilter(): HasMany
    {
        return $this->hasMany(P3GeografischerFilter::class, 'projekt_id');
    }

    public function p3GraueLiteratur(): HasMany
    {
        return $this->hasMany(P3GraueLiteratur::class, 'projekt_id');
    }

    // P4 — Suchstrings
    public function p4Suchstrings(): HasMany
    {
        return $this->hasMany(P4Suchstring::class, 'projekt_id');
    }

    public function p4ThesaurusMapping(): HasMany
    {
        return $this->hasMany(P4ThesaurusMapping::class, 'projekt_id');
    }

    // P5 — Screening (Treffer already defined above)
    public function p5PrismaZahlen(): HasMany
    {
        return $this->hasMany(P5PrismaZahlen::class, 'projekt_id');
    }

    public function p5ScreeningKriterien(): HasMany
    {
        return $this->hasMany(P5ScreeningKriterium::class, 'projekt_id');
    }

    public function p5ToolEntscheidung(): HasMany
    {
        return $this->hasMany(P5ToolEntscheidung::class, 'projekt_id');
    }

    // P6 — Qualitätsbewertung
    public function p6Qualitaetsbewertungen(): HasManyThrough
    {
        return $this->hasManyThrough(
            P6Qualitaetsbewertung::class,
            P5Treffer::class,
            'projekt_id',
            'treffer_id',
        );
    }

    public function p6Luckenanalyse(): HasMany
    {
        return $this->hasMany(P6Luckenanalyse::class, 'projekt_id');
    }

    // P7 — Synthese
    public function p7SyntheseMethode(): HasMany
    {
        return $this->hasMany(P7SyntheseMethode::class, 'projekt_id');
    }

    public function p7GradeEinschaetzung(): HasMany
    {
        return $this->hasMany(P7GradeEinschaetzung::class, 'projekt_id');
    }

    public function p7MusterKonsistenz(): HasMany
    {
        return $this->hasMany(P7MusterKonsistenz::class, 'projekt_id');
    }

    // P8 — Dokumentation
    public function p8Suchprotokolle(): HasManyThrough
    {
        return $this->hasManyThrough(
            P8Suchprotokoll::class,
            P4Suchstring::class,
            'projekt_id',
            'suchstring_id',
        );
    }

    public function p8Limitationen(): HasMany
    {
        return $this->hasMany(P8Limitation::class, 'projekt_id');
    }

    public function p8Reproduzierbarkeitspruefung(): HasMany
    {
        return $this->hasMany(P8Reproduzierbarkeitspruefung::class, 'projekt_id');
    }

    public function p8UpdatePlan(): HasMany
    {
        return $this->hasMany(P8UpdatePlan::class, 'projekt_id');
    }

    // Phase Agent Results
    public function phaseAgentResults(): HasMany
    {
        return $this->hasMany(PhaseAgentResult::class, 'projekt_id');
    }
}

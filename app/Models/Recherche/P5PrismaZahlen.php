<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P5PrismaZahlen extends Model
{
    use HasUuids;

    protected $table = 'p5_prisma_zahlen';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'identifiziert_gesamt',
        'davon_datenbank_treffer',
        'davon_graue_literatur',
        'nach_deduplizierung',
        'ausgeschlossen_l1',
        'volltext_geprueft',
        'ausgeschlossen_l2',
        'eingeschlossen_final',
    ];

    protected $casts = [
        'aktualisiert_am' => 'datetime',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

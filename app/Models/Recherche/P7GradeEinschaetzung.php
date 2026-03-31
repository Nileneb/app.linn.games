<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P7GradeEinschaetzung extends Model
{
    use HasUuids;

    protected $table = 'p7_grade_einschaetzung';
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'outcome',
        'studienanzahl',
        'rob_gesamt',
        'inkonsistenz',
        'indirektheit',
        'impraezision',
        'grade_urteil',
        'begruendung',
    ];

    protected $casts = [
        'studienanzahl' => 'integer',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

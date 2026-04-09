<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P5ToolEntscheidung extends Model
{
    use HasUuids;

    protected $table = 'p5_tool_entscheidung';

    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'tool',
        'gewaehlt',
        'begruendung',
    ];

    protected $casts = [
        'gewaehlt' => 'boolean',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}

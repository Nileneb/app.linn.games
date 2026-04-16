<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'projekt_id',
        'action',
        'enemy_type',
        'cluster_id',
        'paper_id',
        'reaction_ms',
        'wave',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'reaction_ms' => 'integer',
        'wave' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

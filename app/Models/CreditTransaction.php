<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'type',
        'amount_cents',
        'tokens_used',
        'agent_config_key',
        'description',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'tokens_used' => 'integer',
        'created_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}

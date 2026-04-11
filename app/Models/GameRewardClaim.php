<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameRewardClaim extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'kills_threshold',
        'reward_type',
        'reward_value',
        'claimed_at',
    ];

    protected $casts = [
        'kills_threshold' => 'integer',
        'reward_value' => 'float',
        'claimed_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailAlias extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'email', 'verified_at'];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function scopeVerified($query): mixed
    {
        return $query->whereNotNull('verified_at');
    }
}

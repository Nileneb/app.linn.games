<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    protected $fillable = [
        'user_id',
        'ip_anonymous',
        'consent_categories',
        'consented_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_categories' => 'array',
            'consented_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

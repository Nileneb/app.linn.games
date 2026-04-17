<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'forschungsfrage',
        'forschungsbereich',
        'erfahrung',
        'token',
        'token_expires_at',
        'confidence_score',
        'score_breakdown',
        'registration_ip',
        'registration_country_code',
        'registration_country_name',
        'registration_city',
        'user_agent',
        'needs_review',
        'status',
        'expires_at',
    ];

    protected $attributes = [
        'confidence_score' => 0,
        'status' => 'pending_email',
        'needs_review' => false,
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'score_breakdown' => 'array',
            'needs_review' => 'boolean',
            'confidence_score' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }
}

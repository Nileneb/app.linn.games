<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RegistrationAttempt extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'user_agent',
        'reason',
        'email',
        'country_code',
        'country_name',
        'city',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'honeypot' => 'Honeypot',
            'rate_limit' => 'Rate-Limit',
            'validation' => 'Validierungsfehler',
            default => $this->reason,
        };
    }
}

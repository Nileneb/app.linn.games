<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'company',
        'email',
        'project_type',
        'message',
        'timeline',
        'is_spam',
    ];

    protected $casts = [
        'email' => 'string',
        'is_spam' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

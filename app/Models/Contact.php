<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Contact Model
 * 
 * Simple contact form submission storage.
 * Uses auto-increment ID (unlike most other models in this project).
 * This is intentional as Contact represents external submissions, not domain entities.
 */
class Contact extends Model
{
    protected $fillable = [
        'name',
        'company',
        'email',
        'project_type',
        'message',
        'timeline',
    ];

    protected $casts = [
        'email' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

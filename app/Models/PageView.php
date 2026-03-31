<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    protected $fillable = [
        'path',
        'ip_anonymous',
        'user_agent',
        'visits',
    ];
}

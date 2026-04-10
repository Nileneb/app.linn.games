<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GameSession extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'host_user_id', 'status'];

    protected $casts = ['status' => 'string'];

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function isHost(int $userId): bool
    {
        return $this->host_user_id === $userId;
    }
}

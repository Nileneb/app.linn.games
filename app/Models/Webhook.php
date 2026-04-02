<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasUuids;

    protected $table = 'webhooks';
    public $timestamps = false;

    /** Bekannte Frontend-Objekte mit 1:1 Zuordnung pro User */
    public const FRONTEND_OBJECTS = [
        'dashboard_chat'  => 'Dashboard Chat',
        'recherche_start' => 'Recherche starten',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'url',
        'frontend_object',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function callUrl(): string
    {
        return $this->url;
    }

    /** Findet den konfigurierten Webhook eines Users für ein Frontend-Objekt. */
    public static function forUser(int|string $userId, string $frontendObject): ?self
    {
        return static::where('user_id', $userId)
            ->where('frontend_object', $frontendObject)
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}

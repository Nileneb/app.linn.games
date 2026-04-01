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
        'secret',
        'frontend_object',
    ];

    protected $hidden = [
        'secret',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'secret'     => 'encrypted',
    ];

    /**
     * Gibt die Webhook-URL zurück, ggf. mit ?secret=... Query-Parameter.
     * Verwendung: curl -X POST "{{ $webhook->callUrl() }}"
     */
    public function callUrl(): string
    {
        if (! $this->secret) {
            return $this->url;
        }

        $separator = str_contains($this->url, '?') ? '&' : '?';

        return $this->url . $separator . 'secret=' . urlencode($this->secret);
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

<?php

namespace App\Models;

use App\Models\Recherche\Projekt;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhaseAgentResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'projekt_id',
        'user_id',
        'phase_nr',
        'phase',
        'agent_config_key',
        'status',
        'content',
        'error_message',
        'result_data',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'result_data' => 'json',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the latest pending result for a phase (used for polling).
     */
    public static function latestPending(string $projektId, int $phaseNr, string $agentConfigKey): ?self
    {
        return self::where('projekt_id', $projektId)
            ->where('phase_nr', $phaseNr)
            ->where('agent_config_key', $agentConfigKey)
            ->where('status', '!=', 'pending')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Mark result as completed with content.
     */
    public function markCompleted(string $content): void
    {
        $this->update([
            'status' => 'completed',
            'content' => $content,
            'error_message' => null,
        ]);
    }

    /**
     * Mark result as failed with error message.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'content' => null,
        ]);
    }

    /**
     * Mark result as deferred (daily limit reached — auto-retry tomorrow).
     */
    public function markDeferred(string $scheduledFor): void
    {
        $this->update([
            'status' => 'deferred',
            'error_message' => "Tageslimit erreicht — automatischer Retry um {$scheduledFor}",
            'content' => null,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Workspace-scoped LLM-Endpoint-Konfiguration.
 *
 * Wird von MayringCoder per GET /api/mcp-service/llm-endpoint/{workspace_id}?agent=<key>
 * abgefragt und bestimmt, welches LLM-Backend für einen Agent genutzt wird.
 * Workspace kann mehrere Endpoints haben (default + agent-spezifische Overrides).
 *
 * Provider-Werte: 'ollama' | 'anthropic' | 'openai' | 'platform'
 */
class LlmEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'provider',
        'base_url',
        'model',
        'api_key_encrypted',
        'is_default',
        'agent_scope',
        'extra',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'extra' => 'array',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Setter encrypts the plaintext API key at write-time.
     */
    public function setApiKeyAttribute(?string $plain): void
    {
        $this->attributes['api_key_encrypted'] = $plain
            ? Crypt::encryptString($plain)
            : null;
    }

    /**
     * Getter decrypts the stored cipher. Returns null when unset or decryption fails.
     */
    public function getApiKeyAttribute(): ?string
    {
        $cipher = $this->attributes['api_key_encrypted'] ?? null;
        if (! $cipher) {
            return null;
        }

        try {
            return Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Finde den passenden Endpoint für (Workspace, ?agent-key).
     *
     * Priorität:
     *   1. Endpoint mit exakt passendem agent_scope
     *   2. Default-Endpoint (is_default=true, agent_scope=null)
     *   3. null → Caller nutzt 'platform' als Fallback
     */
    public static function resolveFor(Workspace $workspace, ?string $agentKey): ?self
    {
        if ($agentKey !== null) {
            $specific = self::query()
                ->where('workspace_id', $workspace->id)
                ->where('agent_scope', $agentKey)
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        return self::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->whereNull('agent_scope')
            ->first();
    }
}

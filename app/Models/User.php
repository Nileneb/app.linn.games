<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasWorkspaces;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasWorkspaces, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'forschungsfrage',
        'forschungsbereich',
        'erfahrung',
        'invitation_token',
        'invitation_expires_at',
        'provider',
        'provider_id',
        'registration_ip',
        'registration_country_code',
        'registration_country_name',
        'registration_city',
        'total_kills',
        'preferred_chat_model',
        'llm_provider_type',
        'llm_endpoint',
        'llm_api_key',
        'llm_custom_model',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'llm_api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
            'total_kills' => 'integer',
            'llm_api_key' => 'encrypted',
        ];
    }

    public function emailAliases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserEmailAlias::class);
    }

    /**
     * Resolve the Claude chat model this user should use.
     * Falls back to config default when no user preference or preference not in whitelist.
     */
    public function resolvedChatModel(): string
    {
        $available = (array) config('services.anthropic.available_chat_models', []);
        $preferred = $this->preferred_chat_model;

        if ($preferred && isset($available[$preferred])) {
            return $preferred;
        }

        return (string) config('services.anthropic.agent_models.chat-agent',
            config('services.anthropic.model', 'claude-sonnet-4-6')
        );
    }

    /**
     * Resolve the LLM provider configuration for this user's chat.
     *
     * Returns:
     *   - ['type' => 'platform'] when the user relies on the platform's Anthropic key.
     *   - ['type' => 'anthropic-byo', 'api_key' => ..., 'model' => ...] when the user
     *     supplies their own Anthropic API key.
     *   - ['type' => 'openai-compatible', 'endpoint' => ..., 'api_key' => ?, 'model' => ...]
     *     for Ollama/OpenRouter/any OpenAI-compatible endpoint.
     *
     * Validation happens in the settings form. Missing required fields → silently
     * fall back to 'platform' so the chat still works.
     */
    public function llmProviderConfig(): array
    {
        $type = $this->llm_provider_type ?: 'platform';

        if ($type === 'anthropic-byo' && $this->llm_api_key) {
            return [
                'type' => 'anthropic-byo',
                'api_key' => $this->llm_api_key,
                'model' => $this->llm_custom_model ?: $this->resolvedChatModel(),
            ];
        }

        if ($type === 'openai-compatible' && $this->llm_endpoint && $this->llm_custom_model) {
            return [
                'type' => 'openai-compatible',
                'endpoint' => rtrim($this->llm_endpoint, '/'),
                'api_key' => $this->llm_api_key,
                'model' => $this->llm_custom_model,
            ];
        }

        return ['type' => 'platform'];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function isWaitlisted(): bool
    {
        return $this->status === 'waitlisted';
    }

    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    public function hasValidInvitation(): bool
    {
        return $this->invitation_token !== null
            && $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isFuture();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable;

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
            'password' => 'hashed',
            'status' => 'string',
        ];
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

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_users')
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceUser::class, 'user_id');
    }

    public function workspaceRole(string $workspaceId): ?string
    {
        return $this->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->value('role');
    }

    public function activeWorkspaceId(): ?string
    {
        return $this->workspaces()->orderBy('workspaces.created_at')->value('workspaces.id');
    }

    public function ensureDefaultWorkspace(): Workspace
    {
        $workspace = $this->ownedWorkspaces()->first();

        if ($workspace !== null) {
            $hasMembership = $this->workspaceMemberships()
                ->where('workspace_id', $workspace->id)
                ->exists();

            if (! $hasMembership) {
                WorkspaceUser::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $this->id,
                    'role' => 'owner',
                ]);
            }

            return $workspace;
        }

        $workspace = Workspace::create([
            'owner_id' => $this->id,
            'name' => trim($this->name ?: 'Workspace') . ' Workspace',
        ]);

        WorkspaceUser::create([
            'workspace_id' => $workspace->id,
            'user_id' => $this->id,
            'role' => 'owner',
        ]);

        if (! $workspace->exists) {
            throw new RuntimeException('Default workspace could not be created.');
        }

        return $workspace;
    }
}

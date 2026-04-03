<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provides workspace-related relationships and management methods for the User model.
 */
trait HasWorkspaces
{
    private bool $workspaceIdLoaded = false;
    private ?string $cachedWorkspaceId = null;

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
        if (! $this->workspaceIdLoaded) {
            $this->cachedWorkspaceId = $this->workspaces()
                ->orderBy('workspaces.created_at')
                ->value('workspaces.id');
            $this->workspaceIdLoaded = true;
        }

        return $this->cachedWorkspaceId;
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

        app(\App\Services\CreditService::class)->topUp($workspace, 100, 'Startguthaben');

        return $workspace;
    }
}

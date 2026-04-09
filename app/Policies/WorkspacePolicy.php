<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $user->workspaceRole($workspace->id) !== null;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return in_array($user->workspaceRole($workspace->id), ['owner', 'editor'], true);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $user->workspaceRole($workspace->id) === 'owner';
    }
}

<?php

namespace App\Policies;

use App\Models\Recherche\Projekt;
use App\Models\User;

class ProjektPolicy
{
    private function roleForWorkspace(User $user, Projekt $projekt): ?string
    {
        return $user->workspaceRole($projekt->workspace_id);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Projekt $projekt): bool
    {
        return in_array($this->roleForWorkspace($user, $projekt), ['owner', 'editor', 'viewer'], true);
    }

    public function create(User $user): bool
    {
        return $user->activeWorkspaceId() !== null;
    }

    public function update(User $user, Projekt $projekt): bool
    {
        return in_array($this->roleForWorkspace($user, $projekt), ['owner', 'editor'], true);
    }

    public function delete(User $user, Projekt $projekt): bool
    {
        return $this->roleForWorkspace($user, $projekt) === 'owner';
    }
}

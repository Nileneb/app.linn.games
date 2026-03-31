<?php

namespace App\Policies;

use App\Models\Recherche\Projekt;
use App\Models\User;

class ProjektPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Projekt $projekt): bool
    {
        return $user->id === $projekt->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Projekt $projekt): bool
    {
        return $user->id === $projekt->user_id;
    }

    public function delete(User $user, Projekt $projekt): bool
    {
        return $user->id === $projekt->user_id;
    }
}

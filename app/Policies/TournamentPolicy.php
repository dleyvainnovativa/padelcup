<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /** Admin can do anything. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, Tournament $tournament): bool
    {
        return $tournament->manager_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $tournament->manager_id === $user->id;
    }

    public function delete(User $user, Tournament $tournament): bool
    {
        return $tournament->manager_id === $user->id;
    }
}

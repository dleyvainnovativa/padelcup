<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Category $category): bool
    {
        return $category->tournament->manager_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Category $category): bool
    {
        return $category->tournament->manager_id === $user->id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $category->tournament->manager_id === $user->id;
    }
}

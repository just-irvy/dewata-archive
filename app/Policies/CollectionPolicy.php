<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    public function view(?User $user, Collection $collection): bool
    {
        return $collection->status->value === 'published' || ($user?->isAdmin() ?? false);
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, Collection $collection): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->isAdmin();
    }
}

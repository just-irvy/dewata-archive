<?php

namespace App\Policies;

use App\Models\Hotel;
use App\Models\User;

class HotelPolicy
{
    public function view(?User $user, Hotel $hotel): bool
    {
        return $hotel->status->value === 'published' || ($user?->isAdmin() ?? false);
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Hotel $hotel): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, Hotel $hotel): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Hotel $hotel): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Hotel $hotel): bool
    {
        return $user->isSuperAdmin();
    }
}

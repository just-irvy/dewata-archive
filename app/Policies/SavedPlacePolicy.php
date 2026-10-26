<?php

namespace App\Policies;

use App\Models\SavedPlace;
use App\Models\User;

class SavedPlacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, SavedPlace $savedPlace): bool
    {
        return (int) $savedPlace->user_id === (int) $user->getKey();
    }
}

<?php

namespace App\Policies;

use App\Models\TripPlan;
use App\Models\User;

/**
 * Rencana hanya dapat dibaca dan diubah oleh pemiliknya. Admin tidak mendapat
 * akses khusus karena admin tidak perlu bertindak sebagai user (FILE 05).
 * Akses guest lewat token ditangani terpisah di fase planner, bukan di policy ini.
 */
class TripPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, TripPlan $plan): bool
    {
        return $plan->isOwnedBy($user);
    }

    public function update(User $user, TripPlan $plan): bool
    {
        return $plan->isOwnedBy($user);
    }

    public function delete(User $user, TripPlan $plan): bool
    {
        return $plan->isOwnedBy($user);
    }
}

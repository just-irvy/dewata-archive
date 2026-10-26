<?php

namespace App\Policies;

use App\Models\Place;
use App\Models\User;

class PlacePolicy
{
    /** Tempat draft dan archived hanya terlihat oleh admin. Guest boleh melihat yang published. */
    public function view(?User $user, Place $place): bool
    {
        return $place->isPublished() || ($user?->isAdmin() ?? false);
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Place $place): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, Place $place): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Place $place): bool
    {
        return $user->isAdmin();
    }

    /** Hapus permanen hanya untuk super-admin dan hanya bila tempat belum dipakai di rencana. */
    public function forceDelete(User $user, Place $place): bool
    {
        return $user->isSuperAdmin();
    }
}

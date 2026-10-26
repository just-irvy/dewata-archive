<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\SavedPlace;
use App\Models\TripPlan;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tambahkan ke model App\Models\User: `use HasArchiveRole;`
 *
 * Kolom role sengaja tidak masuk $fillable. Role hanya berubah lewat
 * tindakan super-admin yang tercatat di audit log.
 */
trait HasArchiveRole
{
    public function initializeHasArchiveRole(): void
    {
        $this->mergeCasts(['role' => UserRole::class]);
    }

    public function isAdmin(): bool
    {
        return $this->role instanceof UserRole && $this->role->isAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function tripPlans(): HasMany
    {
        return $this->hasMany(TripPlan::class);
    }

    public function savedPlaces(): HasMany
    {
        return $this->hasMany(SavedPlace::class);
    }
}

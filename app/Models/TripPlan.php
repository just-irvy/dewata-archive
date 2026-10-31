<?php

namespace App\Models;

use App\Enums\CreationMode;
use App\Enums\Pace;
use App\Enums\TransportMode;
use App\Enums\TripPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TripPlan extends Model
{
    /**
     * user_id, guest_token_hash, expires_at, dan status tidak mass-assignable.
     * Pemilik ditetapkan lewat relasi ($user->tripPlans()->create) atau Action guest.
     */
    protected $fillable = [
        'title', 'creation_mode', 'start_date', 'days_count', 'origin_region_id',
        'pace', 'transport_mode', 'planner_answers', 'recommendation_version', 'generated_at',
        'generation_notes',
    ];

    protected $hidden = ['guest_token_hash'];

    protected function casts(): array
    {
        return [
            'creation_mode' => CreationMode::class,
            'pace' => Pace::class,
            'transport_mode' => TransportMode::class,
            'status' => TripPlanStatus::class,
            'planner_answers' => 'array',
            'generation_notes' => 'array',
            'start_date' => 'date',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Semua query data pribadi wajib melewati scope ini. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    /** Draft guest yang masih berlaku. Plan milik user tidak punya expires_at. */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && (int) $this->user_id === (int) $user->getKey();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function originRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'origin_region_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(TripPlanDay::class)->orderBy('sort_order');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(TripPlanItem::class, TripPlanDay::class);
    }

    public function isGuestDraft(): bool
    {
        return $this->user_id === null;
    }

    /** Rekomendasi bisa dibuat ulang hanya bila jawaban wizard tersimpan. */
    public function canRecommend(): bool
    {
        return $this->creation_mode === CreationMode::Recommended && is_array($this->planner_answers);
    }
}

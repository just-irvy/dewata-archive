<?php

namespace App\Models;

use App\Enums\PriceBasis;
use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'region_id', 'name', 'slug', 'type', 'address', 'lat', 'lng', 'summary',
        'price_min', 'price_max', 'currency', 'price_basis', 'price_status',
        'source_url', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'price_min' => 'integer',
            'price_max' => 'integer',
            'price_basis' => PriceBasis::class,
            'price_status' => VerificationStatus::class,
            'status' => PublishStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::Published->value);
    }

    public function hasPrice(): bool
    {
        return $this->price_min !== null || $this->price_max !== null;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(HotelPriceSnapshot::class)->latest('checked_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

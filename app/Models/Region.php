<?php

namespace App\Models;

use App\Enums\RegionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'region_type', 'description', 'is_published'];

    protected function casts(): array
    {
        return [
            'region_type' => RegionType::class,
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

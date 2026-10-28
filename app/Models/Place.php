<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * status, published_at, created_by, dan updated_by tidak mass-assignable.
     * Perubahannya lewat Action publikasi agar tercatat di audit log.
     */
    protected $fillable = [
        'region_id', 'area_label', 'name', 'slug', 'summary', 'body', 'lat', 'lng', 'address',
        'verification_status', 'source_url', 'verified_at', 'source_updated_at',
        'external_id', 'attribution', 'typical_visit_minutes', 'price_info',
        'opening_hours', 'opening_hours_status', 'accessibility', 'accessibility_status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'status' => PublishStatus::class,
            'verification_status' => VerificationStatus::class,
            'opening_hours_status' => VerificationStatus::class,
            'accessibility_status' => VerificationStatus::class,
            'opening_hours' => 'array',
            'accessibility' => 'array',
            'verified_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'published_at' => 'datetime',
            'featured_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::Published->value);
    }

    /** Foto utama: foto pertama yang punya alt text dan hak pakai. Relasi media harus sudah dimuat. */
    public function heroMedia(): ?Media
    {
        return $this->media->first(fn (Media $media) => $media->isPublishable());
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'place_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'place_tag');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_places')
            ->withPivot(['sort_order', 'editor_note']);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

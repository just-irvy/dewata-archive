<?php

namespace App\Services\Planner;

use App\Enums\VerificationStatus;
use App\Models\Place;

/** Satu tempat terbit yang sudah dinormalisasi untuk mesin rekomendasi. */
final class Candidate
{
    public float $score = 0.0;

    /** @var array<int, string> nama tema yang cocok dengan pilihan pengguna */
    public array $matchedThemes = [];

    /**
     * @param  array<int, string>  $tagSlugs
     * @param  array<string, string>  $tagNames  slug => nama
     * @param  array<int, int>  $categoryIds
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly int $regionId,
        public readonly string $regionName,
        public readonly ?string $areaLabel,
        public readonly float $lat,
        public readonly float $lng,
        public readonly array $tagSlugs,
        public readonly array $tagNames,
        public readonly array $categoryIds,
        public readonly ?int $visitMinutes,
        public readonly bool $verified,
        public readonly bool $featured,
        public readonly bool $hoursVerified,
        public readonly array $accessibility,
    ) {}

    public static function fromPlace(Place $place): self
    {
        return new self(
            id: $place->id,
            name: $place->name,
            slug: $place->slug,
            regionId: (int) $place->region_id,
            regionName: (string) $place->region?->name,
            areaLabel: $place->area_label,
            lat: (float) $place->lat,
            lng: (float) $place->lng,
            tagSlugs: $place->tags->pluck('slug')->all(),
            tagNames: $place->tags->pluck('name', 'slug')->all(),
            categoryIds: $place->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
            visitMinutes: $place->typical_visit_minutes ? (int) $place->typical_visit_minutes : null,
            verified: $place->verification_status === VerificationStatus::Verified,
            featured: $place->featured_at !== null,
            hoursVerified: $place->opening_hours_status === VerificationStatus::Verified && filled($place->opening_hours),
            accessibility: $place->accessibility_status === VerificationStatus::Verified ? (array) $place->accessibility : [],
        );
    }

    /** Aksesibilitas hanya dihitung bila terverifikasi dan bernilai "yes". */
    public function hasAccess(string $key): bool
    {
        return ($this->accessibility[$key] ?? null) === 'yes';
    }

    public function primaryCategory(): ?int
    {
        return $this->categoryIds[0] ?? null;
    }

    public function minutes(): int
    {
        return $this->visitMinutes ?? (int) config('planner.default_visit_minutes');
    }

    public function km(self|array $other): float
    {
        return is_array($other)
            ? Geo::km($this->lat, $this->lng, $other['lat'], $other['lng'])
            : Geo::km($this->lat, $this->lng, $other->lat, $other->lng);
    }

    /** @return array{lat: float, lng: float} */
    public function point(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }
}

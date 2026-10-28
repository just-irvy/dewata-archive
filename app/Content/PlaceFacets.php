<?php

namespace App\Content;

use App\Enums\TagType;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Pilihan filter yang hanya memuat hal yang benar-benar punya tempat terbit. */
final class PlaceFacets
{
    /** @return Collection<int, Region> */
    public function regions(): Collection
    {
        return Region::query()->published()
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->orderBy('name')->get();
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return Category::query()->published()
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->orderBy('name')->get();
    }

    /** @return Collection<int, Tag> */
    public function themes(): Collection
    {
        return Tag::query()->where('tag_type', TagType::Theme->value)
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->orderBy('name')->get();
    }

    /**
     * Atribut aksesibilitas yang punya minimal satu tempat terbit, terverifikasi, dan bernilai "ya".
     *
     * @return array<string, string> kunci => label
     */
    public function accessibility(): array
    {
        $available = [];

        foreach (config('archive.accessibility_attributes') as $key => $label) {
            $exists = Place::query()->published()
                ->where('accessibility_status', VerificationStatus::Verified->value)
                ->where("accessibility->{$key}", 'yes')
                ->exists();

            if ($exists) {
                $available[$key] = $label;
            }
        }

        return $available;
    }
}

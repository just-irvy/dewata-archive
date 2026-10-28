<?php

namespace App\Content;

use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Tempat terkait: berbagi kategori atau tag, dengan wilayah yang sama didahulukan. */
final class RelatedPlaces
{
    /** @return Collection<int, Place> */
    public function for(Place $place, int $limit = 3): Collection
    {
        $categoryIds = $place->categories()->pluck('categories.id');
        $tagIds = $place->tags()->pluck('tags.id');

        $base = fn (): Builder => Place::query()->published()
            ->whereKeyNot($place->getKey())
            ->with(['region', 'categories', 'media']);

        $sharing = $base()
            ->where(fn (Builder $q) => $q
                ->whereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $categoryIds))
                ->orWhereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $tagIds)))
            ->orderByRaw('case when region_id = ? then 0 else 1 end', [$place->region_id])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        if ($sharing->count() >= $limit) {
            return $sharing;
        }

        $fill = $base()
            ->where('region_id', $place->region_id)
            ->whereNotIn('id', $sharing->modelKeys())
            ->orderByDesc('published_at')
            ->limit($limit - $sharing->count())
            ->get();

        return $sharing->concat($fill)->values();
    }
}

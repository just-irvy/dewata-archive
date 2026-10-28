<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Pagination\AbstractPaginator;

/** Id tempat di halaman ini yang sudah disimpan pengguna. Satu query untuk seluruh halaman. */
final class SavedPlaceIds
{
    /** @return array<int, int> */
    public static function for(?User $user, iterable $places): array
    {
        if ($user === null) {
            return [];
        }

        $items = $places instanceof AbstractPaginator ? $places->getCollection() : collect($places);
        $ids = $items->pluck('id')->filter()->values()->all();

        if ($ids === []) {
            return [];
        }

        return $user->savedPlaces()->whereIn('place_id', $ids)->pluck('place_id')->map(fn ($id) => (int) $id)->all();
    }
}

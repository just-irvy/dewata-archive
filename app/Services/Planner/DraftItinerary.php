<?php

namespace App\Services\Planner;

final class DraftItinerary
{
    /**
     * @param  array<int, DraftDay>  $days
     * @param  array<int, string>  $notes  keterbatasan dan peringatan yang ditampilkan ke pengguna
     */
    public function __construct(
        public readonly array $days,
        public readonly array $notes,
    ) {}

    public function isEmpty(): bool
    {
        foreach ($this->days as $day) {
            if ($day->items !== []) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, int> */
    public function placeIds(): array
    {
        return array_merge([], ...array_map(fn (DraftDay $d) => $d->placeIds(), $this->days));
    }
}

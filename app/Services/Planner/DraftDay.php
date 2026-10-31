<?php

namespace App\Services\Planner;

/** Satu hari hasil rekomendasi sebelum disimpan. */
final class DraftDay
{
    /**
     * @param  array<int, array{candidate: Candidate, reason: string}>  $items
     */
    public function __construct(
        public readonly ?int $regionId,
        public readonly ?string $title,
        public readonly array $items,
    ) {}

    /** @return array<int, int> */
    public function placeIds(): array
    {
        return array_map(fn (array $item) => $item['candidate']->id, $this->items);
    }
}

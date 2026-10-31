<?php

namespace App\Support;

final class Words
{
    /** "A", "A dan B", "A, B, dan C". */
    public static function join(array $items): string
    {
        $items = array_values(array_filter(array_map('strval', $items), fn ($s) => $s !== ''));

        return match (count($items)) {
            0 => '',
            1 => $items[0],
            2 => $items[0].' dan '.$items[1],
            default => implode(', ', array_slice($items, 0, -1)).', dan '.end($items),
        };
    }
}

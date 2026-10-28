<?php

namespace App\Services\Maps;

/**
 * Satu rute harian: asal, tujuan, dan pemberhentian di antaranya.
 *
 * @phpstan-type Point array{lat: float, lng: float}
 */
final class RouteQuery
{
    /**
     * Google menagih lebih tinggi untuk 11 intermediate waypoint ke atas (maksimum 25).
     * Sumber: dokumentasi Routes Library, halaman "Set intermediate waypoints".
     */
    public const MAX_INTERMEDIATES = 10;

    /**
     * @param  array{lat: float, lng: float}  $origin
     * @param  array{lat: float, lng: float}  $destination
     * @param  array<int, array{lat: float, lng: float}>  $intermediates
     */
    public function __construct(
        public readonly array $origin,
        public readonly array $destination,
        public readonly array $intermediates = [],
        public readonly TravelMode $mode = TravelMode::Driving,
    ) {
    }
}

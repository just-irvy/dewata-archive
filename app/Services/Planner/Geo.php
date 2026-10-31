<?php

namespace App\Services\Planner;

/**
 * Jarak garis lurus untuk peringkat kedekatan internal. Nilai ini TIDAK BOLEH ditampilkan
 * sebagai jarak jalan (FILE 09 dan FILE 11). Jarak jalan hanya berasal dari Routes API.
 */
final class Geo
{
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    public static function validPoint(mixed $lat, mixed $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ! ($lat == 0.0 && $lng == 0.0);
    }

    /**
     * @param  iterable<int, array{lat: float, lng: float}|Candidate>  $points
     * @return array{lat: float, lng: float}|null
     */
    public static function centroid(iterable $points): ?array
    {
        $lat = 0.0;
        $lng = 0.0;
        $n = 0;

        foreach ($points as $point) {
            $lat += is_array($point) ? $point['lat'] : $point->lat;
            $lng += is_array($point) ? $point['lng'] : $point->lng;
            $n++;
        }

        return $n === 0 ? null : ['lat' => $lat / $n, 'lng' => $lng / $n];
    }
}

<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\Place;

/** Menyusun data marker untuk komponen <x-map>. Hanya data yang aman tampil di HTML publik. */
final class MapMarkers
{
    /** Peta hanya tampil bila browser key sudah diisi. Tanpa key, halaman memakai tautan Google Maps. */
    public static function enabled(): bool
    {
        return filled(config('archive.maps.browser_key'));
    }

    /**
     * @param  iterable<int, Place>  $places
     * @return array<int, array<string, mixed>>
     */
    public static function forPlaces(iterable $places, bool $numbered = false): array
    {
        $markers = [];
        $n = 0;

        foreach ($places as $place) {
            $n++;
            $markers[] = [
                'id' => 'place-'.$place->id,
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
                'title' => $place->name,
                'label' => $numbered ? (string) $n : null,
                'href' => route('places.show', $place->slug),
                'kind' => 'place',
                'focusId' => 'map-item-'.$place->id,
            ];
        }

        return $markers;
    }

    /** @return array<int, array<string, mixed>> */
    public static function forHotel(Hotel $hotel): array
    {
        return [[
            'id' => 'hotel-'.$hotel->id,
            'lat' => (float) $hotel->lat,
            'lng' => (float) $hotel->lng,
            'title' => $hotel->name,
            'label' => 'H',
            'href' => route('hotels.show', $hotel->slug),
            'kind' => 'hotel',
        ]];
    }
}

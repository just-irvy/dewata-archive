<?php

namespace App\Services\Maps;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Routes API (REST) dari server. Memakai server key terpisah (dibatasi IP), field mask
 * seminimal mungkin, timeout pendek, dan batas harian sebagai pengaman biaya.
 *
 * Respons tidak di-cache. Ketentuan Google membatasi penyimpanan konten Maps, jadi yang
 * disimpan aplikasi hanyalah angka per leg beserta waktu hitungnya di trip_plan_items.
 */
final class GoogleRoutesProvider implements RouteProvider
{
    public const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public const FIELD_MASK = 'routes.duration,routes.distanceMeters,routes.legs.duration,routes.legs.distanceMeters';

    public function compute(RouteQuery $query): RouteResult
    {
        if (count($query->intermediates) > RouteQuery::MAX_INTERMEDIATES) {
            return RouteResult::failed('too_many_stops');
        }

        if (! $this->withinDailyCap()) {
            return RouteResult::failed('quota_reached');
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key' => (string) config('archive.maps.server_key'),
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->post(self::ENDPOINT, $this->body($query));
        } catch (ConnectionException) {
            return RouteResult::failed('unavailable');
        }

        if ($response->status() === 400) {
            return RouteResult::failed('invalid_request');
        }
        if (! $response->successful()) {
            return RouteResult::failed('unavailable');
        }

        $legs = [];
        foreach ($response->json('routes.0.legs', []) as $leg) {
            $legs[] = [
                'distance_m' => (int) ($leg['distanceMeters'] ?? 0),
                'duration_s' => $this->seconds($leg['duration'] ?? '0s'),
            ];
        }

        return $legs === [] ? RouteResult::failed('no_route') : RouteResult::ok($legs);
    }

    /** @return array<string, mixed> */
    private function body(RouteQuery $query): array
    {
        $body = [
            'origin' => $this->waypoint($query->origin),
            'destination' => $this->waypoint($query->destination),
            'intermediates' => array_map(fn (array $point) => $this->waypoint($point), $query->intermediates),
            'travelMode' => $query->mode->rest(),
            'languageCode' => 'id',
            'regionCode' => 'ID',
            'units' => 'METRIC',
        ];

        if ($query->mode->supportsRoutingPreference()) {
            // Tanpa data lalu lintas: tarif lebih murah dan sesuai janji "estimasi, bukan jaminan".
            $body['routingPreference'] = 'TRAFFIC_UNAWARE';
        }

        return $body;
    }

    /** @param  array{lat: float, lng: float}  $point */
    private function waypoint(array $point): array
    {
        return ['location' => ['latLng' => ['latitude' => $point['lat'], 'longitude' => $point['lng']]]];
    }

    /** Durasi dari Google berbentuk string detik, misalnya "905s". */
    private function seconds(string $duration): int
    {
        return (int) round((float) rtrim($duration, 's'));
    }

    private function withinDailyCap(): bool
    {
        $key = 'maps:routes:'.now()->format('Ymd');

        Cache::add($key, 0, now()->endOfDay()->addHour());

        return Cache::increment($key) <= (int) config('archive.maps.routes_daily_cap');
    }
}

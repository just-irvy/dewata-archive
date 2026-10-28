<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Place;
use App\Services\Maps\TravelMode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman uji integrasi peta dan rute dengan data sungguhan. Hanya terdaftar di environment local.
 * Berguna untuk mencoba key, Map ID, dan moda motor sebelum planner dibangun di Fase 4.
 */
class MapsPlaygroundController extends Controller
{
    public function __invoke(Request $request): View
    {
        $mode = TravelMode::tryFrom((string) $request->query('mode')) ?? TravelMode::Driving;
        $return = $request->boolean('kembali');
        $useHotel = $request->boolean('hotel', true);

        $places = Place::query()->published()->orderBy('region_id')->orderBy('name')->limit(6)->get();
        $hotel = $useHotel ? Hotel::query()->published()->orderBy('id')->first() : null;

        $stops = $places->values()->map(fn (Place $place, int $i) => [
            'id' => 'place-'.$place->id,
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'title' => $place->name,
            'label' => 'D1·'.($i + 1),
            'href' => route('places.show', $place->slug),
            'kind' => 'place',
            'focusId' => 'map-item-'.$place->id,
        ])->all();

        $origin = $hotel ? [
            'id' => 'hotel-'.$hotel->id,
            'lat' => (float) $hotel->lat,
            'lng' => (float) $hotel->lng,
            'title' => $hotel->name,
            'label' => 'H',
            'kind' => 'hotel',
        ] : null;

        return view('dev.maps', [
            'places' => $places,
            'hotel' => $hotel,
            'markers' => array_values(array_filter([$origin, ...$stops])),
            'route' => count($stops) >= ($origin ? 1 : 2)
                ? ['origin' => $origin, 'stops' => $stops, 'returnToOrigin' => $return && $origin !== null, 'mode' => $mode->value, 'active' => true]
                : null,
            'mode' => $mode,
            'modes' => TravelMode::cases(),
            'return' => $return,
            'useHotel' => $useHotel,
        ]);
    }
}

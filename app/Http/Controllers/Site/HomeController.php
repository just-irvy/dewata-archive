<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use App\Models\SiteSetting;
use App\Support\SavedPlaceIds;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $with = ['region', 'categories', 'media'];

        // Satu cerita unggulan dipilih editor. Bukan slider otomatis.
        $featured = Place::query()->published()->whereNotNull('featured_at')
            ->with($with)->orderByDesc('featured_at')->first();

        $places = Place::query()->published()->with($with)
            ->when($featured, fn (Builder $q) => $q->whereKeyNot($featured->id))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(6)->get();

        $regions = Region::query()->published()
            ->withCount(['places' => fn (Builder $q) => $q->published()])
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->orderBy('name')->get();

        $hotels = Hotel::query()->published()->with(['region', 'media'])->orderByDesc('id')->limit(3)->get();

        // Titik untuk lembar peta Bali, hanya bila foto sampul belum dipasang.
        $cover = SiteSetting::homeCover();
        $mapPoints = $cover ? collect() : Place::query()->published()
            ->whereNotNull('lat')->whereNotNull('lng')
            ->limit(1000)->get(['lat', 'lng'])
            ->map(fn (Place $p) => [(float) $p->lat, (float) $p->lng]);

        return view('pages.home', [
            'cover' => $cover,
            'mapPoints' => $mapPoints->all(),
            'placeTotal' => $cover ? 0 : Place::query()->published()->count(),
            'featured' => $featured,
            'places' => $places,
            'regions' => $regions,
            'hotels' => $hotels,
            'hotelRegions' => Region::query()->published()
                ->whereHas('hotels', fn (Builder $q) => $q->published())
                ->orderBy('name')->get(),
            'saved' => SavedPlaceIds::for($request->user(), $places->concat($featured ? [$featured] : [])),
            'isEmpty' => $featured === null && $places->isEmpty(),
        ]);
    }
}

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

        // Sorotan pilihan editor, urut dari yang terbaru. Satu sorotan: cerita tunggal.
        // Dua atau tiga: korsel "Pilihan Editor" yang ringkas (kartu tengah tajam, tetangganya buram),
        // tanpa putar otomatis. Paling banyak tiga; sorotan yang lebih lama tidak ditampilkan.
        $picks = Place::query()->published()->whereNotNull('featured_at')
            ->with($with)->orderByDesc('featured_at')->limit(3)->get();
        $featured = $picks->first();

        $places = Place::query()->published()->with($with)
            ->when($picks->isNotEmpty(), fn (Builder $q) => $q->whereNotIn('id', $picks->modelKeys()))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(8)->get();

        $regions = Region::query()->published()
            ->withCount(['places' => fn (Builder $q) => $q->published()])
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->orderBy('name')->get();

        $hotels = Hotel::query()->published()->with(['region', 'media'])->orderByDesc('id')->limit(6)->get();

        // Titik untuk lembar peta Bali, hanya bila foto sampul belum dipasang.
        $cover = SiteSetting::homeCover();
        $mapPoints = $cover ? collect() : Place::query()->published()
            ->whereNotNull('lat')->whereNotNull('lng')
            ->limit(1000)->get(['lat', 'lng'])
            ->map(fn (Place $p) => [(float) $p->lat, (float) $p->lng]);

        // Angka di bilah koleksi (desain Stitch) selalu dihitung dari data terbit, bukan angka hiasan.
        $stats = [
            'places' => Place::query()->published()->count(),
            'hotels' => Hotel::query()->published()->count(),
            'regions' => $regions->count(),
        ];

        return view('pages.home', [
            'cover' => $cover,
            'stats' => $stats,
            'mapPoints' => $mapPoints->all(),
            'placeTotal' => $stats['places'],
            'featured' => $featured,
            'picks' => $picks,
            'places' => $places,
            'regions' => $regions,
            'hotels' => $hotels,
            'hotelRegions' => Region::query()->published()
                ->whereHas('hotels', fn (Builder $q) => $q->published())
                ->orderBy('name')->get(),
            'saved' => SavedPlaceIds::for($request->user(), $places->concat($picks)),
            'isEmpty' => $featured === null && $places->isEmpty(),
        ]);
    }
}

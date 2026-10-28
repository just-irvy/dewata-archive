<?php

namespace App\Http\Controllers\Site;

use App\Content\PlaceSearch;
use App\Enums\RegionType;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Region;
use App\Support\SavedPlaceIds;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(): View
    {
        $regions = Region::query()->published()
            ->withCount(['places' => fn (Builder $q) => $q->published()])
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->orderBy('name')->get();

        return view('pages.regions.index', [
            'administrative' => $regions->where('region_type', RegionType::Administrative)->values(),
            'editorial' => $regions->where('region_type', RegionType::Editorial)->values(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $region = Region::query()->published()->where('slug', $slug)->firstOrFail();

        $places = (new PlaceSearch(['region' => $region->slug]))->query()
            ->paginate(config('archive.per_page'));

        return view('pages.regions.show', [
            'region' => $region,
            'places' => $places,
            'hotels' => Hotel::query()->published()->where('region_id', $region->id)
                ->with(['region', 'media'])->orderBy('name')->limit(4)->get(),
            'saved' => SavedPlaceIds::for($request->user(), $places),
        ]);
    }
}

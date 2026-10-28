<?php

namespace App\Http\Controllers\Site;

use App\Content\HotelSearch;
use App\Enums\HotelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\HotelIndexRequest;
use App\Models\Hotel;
use App\Models\Region;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class HotelController extends Controller
{
    public function index(HotelIndexRequest $request): View
    {
        $filters = $request->filters();

        $hotels = (new HotelSearch($filters))->query()
            ->paginate(config('archive.per_page'))
            ->withQueryString();

        $presentTypes = Hotel::query()->published()->distinct()->pluck('type')
            ->map(fn ($type) => $type instanceof HotelType ? $type : HotelType::from($type));

        return view('pages.hotels.index', [
            'hotels' => $hotels,
            'filters' => $filters,
            'regions' => Region::query()->published()
                ->whereHas('hotels', fn (Builder $q) => $q->published())
                ->orderBy('name')->get(),
            'types' => collect(HotelType::cases())->filter(fn (HotelType $t) => $presentTypes->contains($t))->values(),
        ]);
    }

    public function show(string $slug): View
    {
        $hotel = Hotel::query()->published()->where('slug', $slug)->with(['region', 'media'])->firstOrFail();

        return view('pages.hotels.show', ['hotel' => $hotel]);
    }
}

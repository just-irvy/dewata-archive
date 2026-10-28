<?php

namespace App\Http\Controllers\Site;

use App\Content\PlaceFacets;
use App\Content\PlaceSearch;
use App\Content\RelatedPlaces;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\PlaceIndexRequest;
use App\Models\Place;
use App\Support\SavedPlaceIds;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index(PlaceIndexRequest $request, PlaceFacets $facets): View
    {
        $filters = $request->filters();

        $places = (new PlaceSearch($filters))->query()
            ->paginate(config('archive.per_page'))
            ->withQueryString();

        $regions = $facets->regions();
        $categories = $facets->categories();
        $themes = $facets->themes();
        $access = $facets->accessibility();

        return view('pages.places.index', [
            'places' => $places,
            'filters' => $filters,
            'regions' => $regions,
            'categories' => $categories,
            'themes' => $themes,
            'access' => $access,
            'chips' => $this->chips($filters, $regions, $categories, $themes, $access),
            'saved' => SavedPlaceIds::for($request->user(), $places),
        ]);
    }

    /** Hanya tempat berstatus published. Draf dan arsip selalu 404 di URL publik, juga untuk admin. */
    public function show(Request $request, string $slug, RelatedPlaces $related): View
    {
        $place = Place::query()->published()
            ->where('slug', $slug)
            ->with(['region', 'categories', 'tags', 'media'])
            ->firstOrFail();

        $relatedPlaces = $related->for($place);

        return view('pages.place', [
            'place' => $place,
            'preview' => false,
            'related' => $relatedPlaces,
            'collections' => $place->collections()->published()->orderBy('title')->get(),
            'saved' => SavedPlaceIds::for($request->user(), $relatedPlaces->concat([$place])),
        ]);
    }

    /**
     * Chip filter aktif. Tiap chip menaut ke URL yang sama tanpa filter itu.
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function chips(array $filters, $regions, $categories, $themes, array $access): array
    {
        $params = array_filter([
            'q' => $filters['q'],
            'region' => $filters['region'],
            'category' => $filters['category'],
            'tags' => $filters['tags'],
            'access' => $filters['access'],
            'sort' => $filters['sort'] !== 'terbaru' ? $filters['sort'] : null,
        ]);

        $without = fn (string $key, ?string $value = null) => route('places.index', $value === null
            ? array_diff_key($params, [$key => true])
            : array_replace($params, [$key => array_values(array_diff($params[$key], [$value]))]));

        $chips = [];

        if ($filters['q']) {
            $chips[] = ['label' => 'Kata kunci: '.$filters['q'], 'url' => $without('q')];
        }
        if ($filters['region']) {
            $chips[] = ['label' => 'Wilayah: '.($regions->firstWhere('slug', $filters['region'])?->name ?? $filters['region']), 'url' => $without('region')];
        }
        if ($filters['category']) {
            $chips[] = ['label' => 'Kategori: '.($categories->firstWhere('slug', $filters['category'])?->name ?? $filters['category']), 'url' => $without('category')];
        }
        foreach ($filters['tags'] as $slug) {
            $chips[] = ['label' => 'Tema: '.($themes->firstWhere('slug', $slug)?->name ?? $slug), 'url' => $without('tags', $slug)];
        }
        foreach ($filters['access'] as $key) {
            $chips[] = ['label' => $access[$key] ?? $key, 'url' => $without('access', $key)];
        }

        return $chips;
    }
}

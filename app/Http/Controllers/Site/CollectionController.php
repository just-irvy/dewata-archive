<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Support\SavedPlaceIds;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $collection = Collection::query()->published()->where('slug', $slug)->with('coverMedia')->firstOrFail();

        // Tempat yang dikembalikan ke draf atau diarsipkan menghilang dari koleksi publik.
        $places = $collection->places()->published()->with(['region', 'categories', 'media'])->get();

        return view('pages.collections.show', [
            'collection' => $collection,
            'places' => $places,
            'saved' => SavedPlaceIds::for($request->user(), $places),
        ]);
    }
}

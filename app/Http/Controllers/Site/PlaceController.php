<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Contracts\View\View;

class PlaceController extends Controller
{
    /** Hanya tempat berstatus published. Draf dan arsip selalu 404 di URL publik, juga untuk admin. */
    public function show(string $slug): View
    {
        $place = Place::query()->published()
            ->where('slug', $slug)
            ->with(['region', 'categories', 'tags', 'media'])
            ->firstOrFail();

        return view('pages.place', ['place' => $place, 'preview' => false]);
    }
}

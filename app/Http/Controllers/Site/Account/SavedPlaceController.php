<?php

namespace App\Http\Controllers\Site\Account;

use App\Http\Controllers\Controller;
use App\Models\SavedPlace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SavedPlaceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SavedPlace::class);

        $saved = $request->user()->savedPlaces()
            ->whereHas('place', fn ($q) => $q->published())
            ->with(['place.region', 'place.categories', 'place.media'])
            ->latest('created_at')->latest('id')
            ->paginate(config('archive.per_page'));

        return view('account.saved-places', [
            'saved' => $saved,
            'places' => $saved->getCollection()->pluck('place'),
        ]);
    }
}

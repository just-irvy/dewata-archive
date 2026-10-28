<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\SavedPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookmarkController extends Controller
{
    /**
     * Guest menekan "Simpan": arahkan ke login dan kembalikan ke tempat ini setelahnya.
     * Tidak ada modal dan tidak ada perubahan data pada permintaan GET ini.
     */
    public function prompt(Request $request, string $slug): RedirectResponse
    {
        $place = $this->publishedPlace($slug);

        if ($request->user()) {
            return redirect()->route('places.show', $place->slug);
        }

        $request->session()->put('url.intended', route('places.show', $place->slug));

        return redirect()->route('login');
    }

    /** Idempotent: menyimpan dua kali tetap menghasilkan satu bookmark. */
    public function store(Request $request, string $slug): RedirectResponse
    {
        Gate::authorize('create', SavedPlace::class);

        $place = $this->publishedPlace($slug);
        $request->user()->savedPlaces()->firstOrCreate(['place_id' => $place->id]);

        return redirect()->back(fallback: route('places.show', $place->slug))->with('status', 'Disimpan ke tempat tersimpan.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $place = Place::query()->where('slug', $slug)->firstOrFail();

        $saved = $request->user()->savedPlaces()->where('place_id', $place->id)->first();

        if ($saved) {
            Gate::authorize('delete', $saved);
            $saved->delete();
        }

        return redirect()->back(fallback: route('account.saved-places'))->with('status', 'Dihapus dari tempat tersimpan.');
    }

    private function publishedPlace(string $slug): Place
    {
        return Place::query()->published()->where('slug', $slug)->firstOrFail();
    }
}

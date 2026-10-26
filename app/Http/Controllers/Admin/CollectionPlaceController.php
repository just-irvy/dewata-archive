<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CollectionPlaceRequest;
use App\Models\Collection;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CollectionPlaceController extends Controller
{
    public function store(CollectionPlaceRequest $request, Collection $collection, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('update', $collection);

        $placeId = $request->integer('place_id');
        $order = $request->filled('sort_order')
            ? $request->integer('sort_order')
            : ((int) $collection->places()->reorder()->max('collection_places.sort_order')) + 1;

        $collection->places()->attach($placeId, [
            'sort_order' => $order,
            'editor_note' => $request->input('editor_note'),
        ]);

        $audit('collection.place_added', $collection, null, ['place_id' => $placeId, 'sort_order' => $order]);

        return back()->with('status', 'Tempat ditambahkan ke koleksi.');
    }

    public function update(CollectionPlaceRequest $request, Collection $collection, int $placeId, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('update', $collection);

        $pivot = [
            'sort_order' => $request->integer('sort_order'),
            'editor_note' => $request->input('editor_note'),
        ];

        abort_unless($collection->places()->whereKey($placeId)->exists(), 404);

        $collection->places()->updateExistingPivot($placeId, $pivot);
        $audit('collection.place_updated', $collection, null, ['place_id' => $placeId] + $pivot);

        return back()->with('status', 'Urutan dan catatan disimpan.');
    }

    public function destroy(Collection $collection, int $placeId, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('update', $collection);

        $collection->places()->detach($placeId);

        // Sampul yang berasal dari tempat yang dilepas ikut dikosongkan.
        if ($collection->cover_media_id !== null) {
            $stillValid = Media::query()
                ->whereKey($collection->cover_media_id)
                ->where('mediable_type', Place::class)
                ->whereIn('mediable_id', $collection->places()->pluck('places.id'))
                ->exists();

            if (! $stillValid) {
                $collection->forceFill(['cover_media_id' => null])->save();
            }
        }

        $audit('collection.place_removed', $collection, ['place_id' => $placeId], null);

        return back()->with('status', 'Tempat dilepas dari koleksi.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeStatus;
use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CollectionRequest;
use App\Models\Collection;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CollectionController extends Controller
{
    public function index(): View
    {
        return view('admin.collections.index', [
            'collections' => Collection::query()->withCount('places')->latest('updated_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Collection::class);

        return view('admin.collections.form', ['collection' => new Collection]);
    }

    public function store(CollectionRequest $request, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('create', Collection::class);

        $collection = $save(new Collection, $request->safe()->except('cover_media_id'), $request->user());

        return redirect()->route('admin.collections.edit', $collection->id)
            ->with('status', 'Koleksi disimpan. Tambahkan tempat di bawah.');
    }

    public function edit(Collection $collection): View
    {
        Gate::authorize('update', $collection);

        $collection->load(['places', 'coverMedia']);
        $attachedIds = $collection->places->modelKeys();

        return view('admin.collections.form', [
            'collection' => $collection,
            'candidates' => Place::query()->published()->whereNotIn('id', $attachedIds)->orderBy('name')->get(),
            'coverOptions' => Media::query()
                ->where('mediable_type', Place::class)
                ->whereIn('mediable_id', $attachedIds)
                ->orderBy('mediable_id')->orderBy('sort_order')->get(),
            'report' => ChangeStatus::readinessFor($collection)->check($collection),
        ]);
    }

    public function update(CollectionRequest $request, Collection $collection, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $collection);

        $save($collection, $request->validated(), $request->user());

        return redirect()->route('admin.collections.edit', $collection->id)->with('status', 'Perubahan disimpan.');
    }

    public function destroy(Collection $collection, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('delete', $collection);

        if ($collection->status === PublishStatus::Published) {
            return back()->withErrors(['delete' => 'Koleksi yang terbit tidak bisa dihapus. Arsipkan dulu.']);
        }

        $audit('collection.deleted', $collection, ['title' => $collection->title, 'slug' => $collection->slug], null);
        $collection->delete();

        return redirect()->route('admin.collections.index')->with('status', 'Koleksi dihapus.');
    }
}

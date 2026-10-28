<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeStatus;
use App\Content\PlaceReadiness;
use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlaceRequest;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\TripPlanItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlaceController extends Controller
{
    public function index(Request $request): View
    {
        $status = PublishStatus::tryFrom((string) $request->query('status'));
        $term = trim((string) $request->query('q'));

        $places = Place::query()->with(['region', 'categories', 'media'])
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($request->integer('region'), fn ($q, $region) => $q->where('region_id', $region))
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $readiness = $places->getCollection()
            ->filter(fn (Place $place) => $place->status === PublishStatus::Draft)
            ->mapWithKeys(fn (Place $place) => [$place->id => (new PlaceReadiness)->check($place)]);

        return view('admin.places.index', [
            'places' => $places,
            'readiness' => $readiness,
            'regions' => Region::query()->orderBy('name')->get(),
            'filters' => ['status' => $status?->value, 'region' => $request->integer('region') ?: null, 'q' => $term],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Place::class);

        return view('admin.places.form', $this->formData(new Place));
    }

    public function store(PlaceRequest $request, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('create', Place::class);

        $place = $save(
            new Place,
            $request->placeData(),
            $request->user(),
            forced: ['updated_by' => $request->user()->id, 'featured_at' => $request->boolean('is_featured') ? now() : null],
            forcedOnCreate: ['created_by' => $request->user()->id],
            afterSave: fn (Place $place) => $this->syncRelations($place, $request),
        );

        return redirect()->route('admin.places.edit', $place->id)->with('status', 'Draf tempat disimpan.');
    }

    public function edit(Place $place): View
    {
        Gate::authorize('update', $place);

        $place->load(['categories', 'tags', 'media']);

        return view('admin.places.form', $this->formData($place) + [
            'report' => ChangeStatus::readinessFor($place)->check($place),
        ]);
    }

    public function update(PlaceRequest $request, Place $place, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $place);

        $save(
            $place,
            $request->placeData(),
            $request->user(),
            forced: ['updated_by' => $request->user()->id, 'featured_at' => $request->boolean('is_featured') ? ($place->featured_at ?? now()) : null],
            afterSave: fn (Place $place) => $this->syncRelations($place, $request),
        );

        return redirect()->route('admin.places.edit', $place->id)->with('status', 'Perubahan disimpan.');
    }

    public function destroy(Request $request, Place $place, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('delete', $place);

        if ($place->isPublished()) {
            return back()->withErrors(['delete' => 'Tempat yang terbit tidak bisa dihapus. Arsipkan dulu.']);
        }
        if (TripPlanItem::query()->where('place_id', $place->id)->exists()) {
            return back()->withErrors(['delete' => 'Tempat ini sudah dipakai di rencana pengguna. Arsipkan, jangan hapus.']);
        }

        $audit('place.deleted', $place, ['name' => $place->name, 'slug' => $place->slug], null);
        $place->delete();

        return redirect()->route('admin.places.index')->with('status', 'Tempat dihapus.');
    }

    /** Cangkang pratinjau: menampilkan halaman publik di iframe dengan lebar desktop atau ponsel. */
    public function preview(Place $place): View
    {
        Gate::authorize('view', $place);

        return view('admin.places.preview', ['place' => $place]);
    }

    public function previewPage(Place $place): View
    {
        Gate::authorize('view', $place);

        return view('pages.place', [
            'place' => $place->load(['region', 'categories', 'tags', 'media']),
            'preview' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(Place $place): array
    {
        return [
            'place' => $place,
            'regions' => Region::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('tag_type')->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> Perubahan pivot untuk dicatat di audit. */
    private function syncRelations(Place $place, PlaceRequest $request): array
    {
        $extra = [];

        foreach (['categories' => $request->categoryIds(), 'tags' => $request->tagIds()] as $relation => $ids) {
            $result = $place->{$relation}()->sync($ids);

            if ($result['attached'] !== [] || $result['detached'] !== []) {
                $extra[$relation] = ['attached' => $result['attached'], 'detached' => $result['detached']];
            }
        }

        return $extra;
    }
}

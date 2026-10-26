<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaStoreRequest;
use App\Http\Requests\Admin\MediaUpdateRequest;
use App\Models\Hotel;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Media::class);

        $incomplete = $request->boolean('incomplete');

        $media = Media::query()->with('mediable')
            ->when($incomplete, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('alt_text')
                ->orWhere(fn ($x) => $x->whereNull('credit')->whereNull('license_note'))))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('admin.media.index', ['media' => $media, 'incomplete' => $incomplete]);
    }

    public function store(MediaStoreRequest $request, RecordAudit $audit): RedirectResponse
    {
        $owner = $request->mediable();
        Gate::authorize('update', $owner);

        $file = $request->file('file');

        // Nama acak dan ekstensi dari MIME hasil deteksi server, bukan dari nama berkas pengguna.
        $path = $file->storeAs(
            'media/'.now()->format('Y/m'),
            Str::random(40).'.'.$file->extension(),
            config('archive.media.disk'),
        );

        $size = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = new Media(Arr::only($request->validated(), ['alt_text', 'caption', 'credit', 'license_note']) + [
            'path' => $path,
            'width' => $size[0],
            'height' => $size[1],
            'sort_order' => $request->filled('sort_order')
                ? $request->integer('sort_order')
                : ((int) $owner->media()->reorder()->max('sort_order')) + 1,
        ]);
        $media->forceFill(['uploaded_by' => $request->user()->id]);
        $owner->media()->save($media);

        $audit('media.created', $media, null, [
            'path' => $path,
            'mediable_type' => $media->mediable_type,
            'mediable_id' => $media->mediable_id,
        ]);

        return $this->backToOwner($owner)->with('status', 'Foto diunggah.');
    }

    public function update(MediaUpdateRequest $request, Media $media, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $media);

        $save($media, $request->validated(), $request->user());

        return $this->backToOwner($media->mediable)->with('status', 'Keterangan foto disimpan.');
    }

    public function destroy(Media $media, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('delete', $media);

        $owner = $media->mediable;

        $audit('media.deleted', $media, ['path' => $media->path], null);
        Storage::disk(config('archive.media.disk'))->delete($media->path);
        $media->delete();

        return $this->backToOwner($owner)->with('status', 'Foto dihapus.');
    }

    private function backToOwner(mixed $owner): RedirectResponse
    {
        $route = $owner instanceof Hotel ? 'admin.hotels.edit' : 'admin.places.edit';

        return redirect()->to(route($route, $owner->id).'#foto');
    }
}

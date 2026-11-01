<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeCoverRequest;
use App\Models\SiteSetting;
use App\Photos\ImageProcessor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Admin: foto sampul Pulau Bali di beranda. */
class HomeCoverController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('manage-site');

        return view('admin.home.edit', [
            'cover' => SiteSetting::value(SiteSetting::HOME_COVER),
            'live' => SiteSetting::homeCover(),
        ]);
    }

    public function update(HomeCoverRequest $request, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('manage-site');

        $before = SiteSetting::value(SiteSetting::HOME_COVER);
        $value = Arr::only($request->validated(), ['alt_text', 'caption', 'credit', 'license_note']) + Arr::only($before ?? [], ['path', 'width', 'height']);
        $disk = Storage::disk(config('archive.media.disk'));

        if ($request->hasFile('file')) {
            // Diolah ulang seperti impor foto: orientasi diperbaiki, metadata (termasuk GPS) dibuang,
            // ukuran dibatasi. Nama acak, ekstensi dari isi berkas.
            $source = $request->file('file')->getRealPath();
            $tmp = tempnam(sys_get_temp_dir(), 'cover');
            $result = ImageProcessor::process($source, $tmp, 2400);
            $path = 'site/'.Str::random(40).'.'.$result['ext'];
            $disk->put($path, file_get_contents($tmp));
            @unlink($tmp);

            if (filled($before['path'] ?? null)) {
                $disk->delete($before['path']);
            }

            $value['path'] = $path;
            $value['width'] = $result['width'] ?? null;
            $value['height'] = $result['height'] ?? null;
        }

        $value['updated_by'] = $request->user()->id;
        $setting = SiteSetting::put(SiteSetting::HOME_COVER, $value);
        $audit('site.home_cover.updated', $setting, $before, $value);

        return redirect()->route('admin.home.edit')->with('status', 'Foto sampul beranda disimpan.');
    }

    public function destroy(RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('manage-site');

        $before = SiteSetting::value(SiteSetting::HOME_COVER);
        if (filled($before['path'] ?? null)) {
            Storage::disk(config('archive.media.disk'))->delete($before['path']);
        }
        $setting = SiteSetting::put(SiteSetting::HOME_COVER, null);
        $audit('site.home_cover.deleted', $setting, $before, null);

        return redirect()->route('admin.home.edit')->with('status', 'Foto sampul dihapus. Beranda kembali menampilkan peta Bali.');
    }
}

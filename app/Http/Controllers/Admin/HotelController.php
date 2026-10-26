<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeStatus;
use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HotelRequest;
use App\Models\Hotel;
use App\Models\Region;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HotelController extends Controller
{
    public function index(Request $request): View
    {
        $status = PublishStatus::tryFrom((string) $request->query('status'));
        $term = trim((string) $request->query('q'));

        $hotels = Hotel::query()->with('region')
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($request->integer('region'), fn ($q, $region) => $q->where('region_id', $region))
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.hotels.index', [
            'hotels' => $hotels,
            'regions' => Region::query()->orderBy('name')->get(),
            'filters' => ['status' => $status?->value, 'region' => $request->integer('region') ?: null, 'q' => $term],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Hotel::class);

        return view('admin.hotels.form', $this->formData(new Hotel));
    }

    public function store(HotelRequest $request, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('create', Hotel::class);

        $hotel = $save(new Hotel, $request->hotelData(), $request->user(), afterSave: $this->snapshotPrice(...));

        return redirect()->route('admin.hotels.edit', $hotel->id)->with('status', 'Draf hotel disimpan.');
    }

    public function edit(Hotel $hotel): View
    {
        Gate::authorize('update', $hotel);

        $hotel->load(['media', 'priceSnapshots']);

        return view('admin.hotels.form', $this->formData($hotel) + [
            'report' => ChangeStatus::readinessFor($hotel)->check($hotel),
        ]);
    }

    public function update(HotelRequest $request, Hotel $hotel, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $hotel);

        $save($hotel, $request->hotelData(), $request->user(), afterSave: $this->snapshotPrice(...));

        return redirect()->route('admin.hotels.edit', $hotel->id)->with('status', 'Perubahan disimpan.');
    }

    public function destroy(Hotel $hotel, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('delete', $hotel);

        if ($hotel->status === PublishStatus::Published) {
            return back()->withErrors(['delete' => 'Hotel yang terbit tidak bisa dihapus. Arsipkan dulu.']);
        }

        $audit('hotel.deleted', $hotel, ['name' => $hotel->name, 'slug' => $hotel->slug], null);
        $hotel->delete();

        return redirect()->route('admin.hotels.index')->with('status', 'Hotel dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(Hotel $hotel): array
    {
        return ['hotel' => $hotel, 'regions' => Region::query()->orderBy('name')->get()];
    }

    /**
     * Setiap perubahan harga menambah satu baris riwayat verifikasi.
     *
     * @param  array<int, string>  $changed
     * @return array<string, mixed>
     */
    private function snapshotPrice(Hotel $hotel, array $changed): array
    {
        if (! array_intersect(['price_min', 'price_max', 'price_basis'], $changed) || ! $hotel->hasPrice()) {
            return [];
        }

        $hotel->priceSnapshots()->create([
            'price_min' => $hotel->price_min,
            'price_max' => $hotel->price_max,
            'currency' => $hotel->currency ?? 'IDR',
            'price_basis' => $hotel->price_basis,
            'source_url' => $hotel->source_url,
            'checked_at' => $hotel->verified_at ?? now(),
        ]);

        return ['price_snapshot' => true];
    }
}

<?php

namespace App\Actions;

use App\Models\Hotel;
use App\Models\Media;
use App\Models\Place;
use App\Models\TripPlanDay;
use App\Models\TripPlanItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus PERMANEN semua tempat dan hotel, termasuk yang terbit, yang diarsipkan, dan yang
 * sudah dihapus sementara, beserta fotonya. Dipakai untuk mengganti katalog dari nol.
 *
 * Yang ikut terhapus: kaitan kategori/tag, isi koleksi (koleksinya tetap ada), bookmark pengguna,
 * riwayat harga hotel, foto beserta berkasnya, dan tempat di dalam rencana pengguna.
 * Rencana pengguna sendiri tidak dihapus; hari-harinya tetap ada tetapi kosong, dan penginapan
 * titik awalnya dikosongkan. Wilayah, kategori, tag, akun, dan audit log tidak disentuh.
 */
final class ResetCatalog
{
    public const CONFIRMATION = 'HAPUS SEMUA';

    public function __construct(private readonly RecordAudit $audit) {}

    /** @return array<string, int> */
    public function preview(): array
    {
        $types = [Place::class, Hotel::class];

        return [
            'places' => Place::withTrashed()->count(),
            'hotels' => Hotel::withTrashed()->count(),
            'media' => Media::query()->whereIn('mediable_type', $types)->count(),
            'plan_items' => TripPlanItem::query()->count(),
            'plans_affected' => TripPlanDay::query()
                ->where(fn ($q) => $q->whereHas('items')->orWhereNotNull('hotel_id'))
                ->distinct()->count('trip_plan_id'),
            'saved_places' => DB::table('saved_places')->count(),
            'collection_links' => DB::table('collection_places')->count(),
        ];
    }

    /** @return array<string, int> jumlah yang dihapus */
    public function __invoke(User $actor): array
    {
        $counts = $this->preview();
        $types = [Place::class, Hotel::class];
        $paths = Media::query()->whereIn('mediable_type', $types)->pluck('path')->all();

        DB::transaction(function () use ($types) {
            // trip_plan_items.place_id memakai restrictOnDelete, jadi item dihapus lebih dulu.
            TripPlanItem::query()->delete();
            Media::query()->whereIn('mediable_type', $types)->delete();

            // Pivot, koleksi, bookmark, dan riwayat harga terhapus lewat cascade di database.
            // trip_plan_days.hotel_id menjadi null lewat nullOnDelete.
            Place::withTrashed()->forceDelete();
            Hotel::withTrashed()->forceDelete();
        });

        // Berkas dihapus setelah transaksi berhasil, agar kegagalan database tidak meninggalkan
        // record yang menunjuk ke foto yang sudah hilang.
        $disk = Storage::disk(config('archive.media.disk'));
        foreach (array_chunk($paths, 200) as $chunk) {
            $disk->delete($chunk);
        }

        Cache::forget('archive.last_content_update');

        ($this->audit)('catalog.reset', $actor, null, $counts + ['actor' => $actor->email]);

        return $counts;
    }
}

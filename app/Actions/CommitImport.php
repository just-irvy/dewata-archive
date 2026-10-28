<?php

namespace App\Actions;

use App\Enums\HotelType;
use App\Enums\PriceBasis;
use App\Enums\PublishStatus;
use App\Enums\TagType;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Hotel;
use App\Models\ImportBatch;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 5 FILE 16: menyetujui dan membuat record. Hanya membuat DRAF.
 * Idempotent: baris yang external_id-nya sudah ada dilewati, bukan ditimpa.
 * Dataset ini tidak memuat sumber maupun tanggal verifikasi, jadi tidak ada yang terbit otomatis.
 */
final class CommitImport
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @return array{created_places: int, created_hotels: int, skipped: int, errors: int} */
    public function __invoke(ImportBatch $batch, User $actor): array
    {
        if (! $batch->isStaged()) {
            throw new DomainException('Batch ini sudah diproses.');
        }

        return DB::transaction(function () use ($batch, $actor) {
            $regions = Region::query()->published()->pluck('id', 'slug');
            $counts = ['created_places' => 0, 'created_hotels' => 0, 'skipped' => 0, 'errors' => 0];

            foreach ($batch->rows()->get() as $row) {
                if ($row->status === 'error') {
                    $counts['errors']++;

                    continue;
                }

                $payload = $row->payload;

                // Cek ulang saat commit: record bisa muncul di antara staging dan persetujuan.
                if ($row->action === 'skip' || $this->exists($row->entity, $payload['external_id'])) {
                    $row->update(['action' => 'skip']);
                    $counts['skipped']++;

                    continue;
                }

                $regionId = $regions[$payload['region_slug']] ?? null;
                if ($regionId === null) {
                    $row->update(['status' => 'error', 'action' => 'skip']);
                    $counts['errors']++;

                    continue;
                }

                $record = $row->entity === 'hotel'
                    ? $this->createHotel($payload, $regionId, $actor)
                    : $this->createPlace($payload, $regionId, $actor);

                $row->update(['action' => 'created', 'record_id' => $record->id]);
                $counts[$row->entity === 'hotel' ? 'created_hotels' : 'created_places']++;
            }

            $batch->forceFill([
                'status' => 'committed',
                'committed_by' => $actor->id,
                'committed_at' => now(),
                'totals' => ($batch->totals ?? []) + ['result' => $counts],
            ])->save();

            ($this->audit)('import_batch.committed', $batch, ['status' => 'staged'], ['status' => 'committed'] + $counts);

            return $counts;
        });
    }

    private function exists(?string $entity, ?string $externalId): bool
    {
        if ($externalId === null) {
            return false;
        }

        $model = $entity === 'hotel' ? Hotel::class : Place::class;

        return $model::withTrashed()->where('external_id', $externalId)->exists();
    }

    /** @param  array<string, mixed>  $p */
    private function createPlace(array $p, int $regionId, User $actor): Place
    {
        $place = new Place;
        $place->forceFill([
            'region_id' => $regionId,
            'area_label' => $p['area_label'],
            'name' => $p['name'],
            'slug' => $this->freeSlug(Place::class, $p['slug']),
            'summary' => $p['summary'],
            'lat' => $p['lat'],
            'lng' => $p['lng'],
            'external_id' => $p['external_id'],
            'source_url' => $p['source_url'] ?? null,
            'source_updated_at' => $p['source_updated_at'] ?? null,
            'verified_at' => $p['verified_at'] ?? null,
            'status' => PublishStatus::Draft,
            'verification_status' => VerificationStatus::tryFrom((string) ($p['verification_status'] ?? '')) ?? VerificationStatus::Unknown,
            'opening_hours_status' => VerificationStatus::Unknown,
            'accessibility_status' => VerificationStatus::Unknown,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        if (! empty($p['category'])) {
            $category = Category::query()->firstOrCreate(
                ['slug' => $p['category']['slug']],
                ['name' => $p['category']['name'], 'is_published' => true],
            );
            $place->categories()->attach($category);
        }

        if (! empty($p['tag'])) {
            $tag = Tag::query()->firstOrCreate(
                ['slug' => $p['tag']['slug']],
                ['name' => $p['tag']['name'], 'tag_type' => TagType::Theme, 'description' => $p['tag']['description'] ?? null],
            );
            $place->tags()->attach($tag);
        }

        return $place;
    }

    /** @param  array<string, mixed>  $p */
    private function createHotel(array $p, int $regionId, User $actor): Hotel
    {
        $price = $p['price_min'] ?? null;
        $basis = $p['price_basis'] ?? null;

        $hotel = new Hotel;
        $hotel->forceFill([
            'region_id' => $regionId,
            'area_label' => $p['area_label'],
            'name' => $p['name'],
            'slug' => $this->freeSlug(Hotel::class, $p['slug']),
            'external_id' => $p['external_id'],
            'type' => HotelType::tryFrom($p['hotel_type'] ?? 'hotel') ?? HotelType::Hotel,
            'source_url' => $p['source_url'] ?? null,
            'verified_at' => $p['verified_at'] ?? null,
            'summary' => $p['summary'],
            'lat' => $p['lat'],
            'lng' => $p['lng'],
            'price_min' => $price,
            'price_max' => $p['price_max'] ?? null,
            'currency' => 'IDR',
            // Jenis sumber dari dataset (penyedia atau estimasi). Tanpa tanggal harga, statusnya tetap belum diketahui.
            'price_basis' => $price ? (PriceBasis::tryFrom((string) $basis) ?? PriceBasis::Estimate) : null,
            'price_status' => VerificationStatus::Unknown,
            'status' => PublishStatus::Draft,
        ])->save();

        return $hotel;
    }

    /** @param  class-string<Model>  $model */
    private function freeSlug(string $model, string $slug): string
    {
        $candidate = $slug;

        for ($i = 2; $model::withTrashed()->where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }
}

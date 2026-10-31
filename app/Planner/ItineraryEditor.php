<?php

namespace App\Planner;

use App\Enums\ItemSource;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\TripPlan;
use App\Models\TripPlanDay;
use App\Models\TripPlanItem;
use App\Services\Planner\Geo;
use App\Services\Planner\PlannerPreferences;
use App\Services\Planner\RecommendationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editor itinerary bersama untuk mode Rekomendasi dan Manual (FILE 10).
 *
 * Aturan yang ditegakkan di sini, bukan di UI: tempat harus terbit, tidak boleh ganda dalam
 * satu arsip, hari tidak melebihi batas tempat, jumlah hari dalam rentang produk, dan urutan
 * selalu rapat 1..n. Pemanggil wajib sudah memastikan hari dan item milik rencana ini.
 *
 * item_source hanya provenance. Item rekomendasi bisa diedit, dipindah, dan dihapus seperti
 * item manual.
 */
final class ItineraryEditor
{
    public function __construct(
        private readonly RecommendationEngine $engine,
        private readonly PlanFactory $factory,
    ) {}

    public function addItem(TripPlan $plan, TripPlanDay $day, Place $place, ?string $note = null): TripPlanItem
    {
        $this->assertPublished($place);
        $this->assertNotInPlan($plan, $place);
        $this->assertRoom($day);

        return DB::transaction(function () use ($day, $place, $note) {
            $item = $day->items()->create([
                'place_id' => $place->id,
                'item_source' => ItemSource::Manual,
                'duration_minutes' => $place->typical_visit_minutes,
                'note' => $note,
                'sort_order' => $day->items()->count() + 1,
            ]);
            $this->clearRoutes($day);

            return $item;
        });
    }

    /** Catatan, durasi, dan jam mulai tidak mengubah geografi, jadi rute tidak dihitung ulang. */
    public function updateItem(TripPlanItem $item, array $data): void
    {
        $item->fill([
            'note' => $data['note'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'start_time' => $data['start_time'] ?? null,
        ])->save();
    }

    /** Memindah ke hari lain atau ke posisi lain (1-based). Posisi null berarti paling akhir. */
    public function moveItem(TripPlanItem $item, TripPlanDay $target, ?int $position = null): void
    {
        $source = $item->day;

        if ($source->id !== $target->id) {
            $this->assertRoom($target);
        }

        DB::transaction(function () use ($item, $source, $target, $position) {
            $ids = $target->items()->whereKeyNot($item->id)->pluck('id')->all();
            $position = $position === null ? count($ids) + 1 : max(1, min($position, count($ids) + 1));
            array_splice($ids, $position - 1, 0, [$item->id]);

            if ($source->id !== $target->id) {
                $item->forceFill(['trip_plan_day_id' => $target->id])->save();
                $this->renumber($source);
                $this->clearRoutes($source);
            }

            foreach ($ids as $i => $id) {
                TripPlanItem::query()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
            $this->clearRoutes($target);
        });
    }

    public function shiftItem(TripPlanItem $item, string $direction): void
    {
        $day = $item->day;
        $ids = $day->items()->pluck('id')->all();
        $index = array_search($item->id, $ids, true);
        $position = $direction === 'up' ? $index : $index + 2;

        if ($position < 1 || $position > count($ids)) {
            return;
        }

        $this->moveItem($item, $day, $position);
    }

    public function removeItem(TripPlanItem $item): void
    {
        $day = $item->day;

        DB::transaction(function () use ($item, $day) {
            $item->delete();
            $this->renumber($day);
            $this->clearRoutes($day);
        });
    }

    /** Pengganti dari cadangan rekomendasi. Posisi tetap, alasan dihitung ulang dari data. */
    public function replaceItem(TripPlan $plan, TripPlanItem $item, Place $place): void
    {
        $this->assertPublished($place);
        $this->assertNotInPlan($plan, $place);

        $reason = null;
        if ($plan->canRecommend()) {
            $prefs = PlannerPreferences::fromArray($plan->planner_answers);
            $match = collect($this->engine->alternatives($prefs, [], null, PHP_INT_MAX))
                ->first(fn ($alt) => $alt['candidate']->id === $place->id);
            $reason = $match['reason'] ?? null;
        }

        DB::transaction(function () use ($item, $place, $reason) {
            $item->fill([
                'place_id' => $place->id,
                'item_source' => $reason ? ItemSource::Recommended : ItemSource::Manual,
                'recommendation_reason' => $reason,
                'duration_minutes' => $place->typical_visit_minutes,
                'start_time' => null,
            ])->save();
            $this->clearRoutes($item->day);
        });
    }

    public function addDay(TripPlan $plan): TripPlanDay
    {
        $count = $plan->days()->count();
        if ($count >= (int) config('planner.max_days')) {
            throw ValidationException::withMessages(['day' => 'Arsip paling banyak '.config('planner.max_days').' hari.']);
        }

        return DB::transaction(function () use ($plan, $count) {
            $day = $plan->days()->create([
                'day_number' => $count + 1,
                'sort_order' => $count + 1,
                'date' => $this->factory->dateFor($plan->start_date?->toDateString(), $count + 1),
            ]);
            $plan->forceFill(['days_count' => $count + 1])->save();

            return $day;
        });
    }

    public function removeDay(TripPlan $plan, TripPlanDay $day): void
    {
        if ($plan->days()->count() <= 1) {
            throw ValidationException::withMessages(['day' => 'Arsip membutuhkan minimal satu hari.']);
        }

        DB::transaction(function () use ($plan, $day) {
            $day->delete();
            $this->renumberDays($plan);
        });
    }

    public function updateDay(TripPlanDay $day, array $data): void
    {
        $hotelChanged = array_key_exists('hotel_id', $data) && (int) $day->hotel_id !== (int) $data['hotel_id'];
        $returnChanged = array_key_exists('return_to_hotel', $data) && (bool) $day->return_to_hotel !== (bool) $data['return_to_hotel'];

        $day->fill([
            'title' => array_key_exists('title', $data) ? $data['title'] : $day->title,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $day->notes,
            'hotel_id' => array_key_exists('hotel_id', $data) ? $data['hotel_id'] : $day->hotel_id,
            'return_to_hotel' => array_key_exists('return_to_hotel', $data) ? (bool) $data['return_to_hotel'] : $day->return_to_hotel,
        ]);
        if ($day->hotel_id === null) {
            $day->return_to_hotel = false;
        }
        $day->save();

        if ($hotelChanged || $returnChanged) {
            $this->clearRoutes($day);
        }
    }

    public function setHotelForAllDays(TripPlan $plan, Hotel $hotel): void
    {
        DB::transaction(function () use ($plan, $hotel) {
            foreach ($plan->days as $day) {
                $day->forceFill(['hotel_id' => $hotel->id])->save();
                $this->clearRoutes($day);
            }
        });
    }

    public function updatePlan(TripPlan $plan, array $data): void
    {
        DB::transaction(function () use ($plan, $data) {
            $transportChanged = array_key_exists('transport_mode', $data)
                && $plan->transport_mode?->value !== ($data['transport_mode'] ?? null);

            $plan->fill([
                'title' => $data['title'],
                'start_date' => $data['start_date'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
            ])->save();

            $this->renumberDays($plan);

            if ($transportChanged) {
                foreach ($plan->days as $day) {
                    $this->clearRoutes($day);
                }
            }
        });
    }

    /**
     * Menyusun ulang satu hari dari jawaban wizard. Tempat di hari lain tidak dipakai lagi.
     * Isi hari itu diganti, termasuk tempat yang ditambahkan manual. UI menyatakan hal ini.
     */
    public function regenerateDay(TripPlan $plan, TripPlanDay $day): int
    {
        if (! $plan->canRecommend()) {
            throw ValidationException::withMessages(['day' => 'Hanya arsip dari rekomendasi yang bisa disusun ulang.']);
        }

        $prefs = PlannerPreferences::fromArray($plan->planner_answers);
        $exclude = TripPlanItem::query()
            ->whereIn('trip_plan_day_id', $plan->days()->whereKeyNot($day->id)->pluck('id'))
            ->pluck('place_id')->map(fn ($id) => (int) $id)->all();

        $draft = $this->engine->rebuildDay($prefs, $day->region_id, $exclude);

        DB::transaction(function () use ($day, $draft) {
            $day->items()->delete();
            foreach ($draft->items as $i => $item) {
                $day->items()->create([
                    'place_id' => $item['candidate']->id,
                    'item_source' => ItemSource::Recommended,
                    'recommendation_reason' => $item['reason'],
                    'duration_minutes' => $item['candidate']->visitMinutes,
                    'sort_order' => $i + 1,
                ]);
            }
            $day->fill(['region_id' => $draft->regionId ?? $day->region_id, 'title' => $draft->title ?? $day->title])->save();
        });

        return count($draft->items);
    }

    /** Titik tengah tempat pada satu hari, untuk mencari cadangan yang dekat. */
    public function dayCentroid(TripPlanDay $day): ?array
    {
        return Geo::centroid($day->items->map(fn (TripPlanItem $i) => ['lat' => (float) $i->place->lat, 'lng' => (float) $i->place->lng]));
    }

    private function assertPublished(Place $place): void
    {
        if (! $place->isPublished() || ! Geo::validPoint($place->lat, $place->lng)) {
            throw ValidationException::withMessages(['place_id' => 'Hanya tempat terbit dengan lokasi valid yang bisa ditambahkan.']);
        }
    }

    private function assertNotInPlan(TripPlan $plan, Place $place): void
    {
        $existing = TripPlanItem::query()
            ->whereIn('trip_plan_day_id', $plan->days()->pluck('id'))
            ->where('place_id', $place->id)
            ->with('day')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['place_id' => $place->name.' sudah ada di Hari '.$existing->day->day_number.'.']);
        }
    }

    private function assertRoom(TripPlanDay $day): void
    {
        $max = (int) config('planner.max_items_per_day');
        if ($day->items()->count() >= $max) {
            throw ValidationException::withMessages(['day' => 'Hari '.$day->day_number.' sudah berisi '.$max.' tempat, batas satu rute harian.']);
        }
    }

    private function renumber(TripPlanDay $day): void
    {
        foreach ($day->items()->pluck('id') as $i => $id) {
            TripPlanItem::query()->whereKey($id)->update(['sort_order' => $i + 1]);
        }
    }

    private function renumberDays(TripPlan $plan): void
    {
        $days = $plan->days()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($days as $i => $day) {
            $day->forceFill([
                'day_number' => $i + 1,
                'sort_order' => $i + 1,
                'date' => $this->factory->dateFor($plan->start_date?->toDateString(), $i + 1),
            ])->save();
        }

        $plan->forceFill(['days_count' => $days->count()])->save();
    }

    /** Snapshot jarak per leg tidak berlaku lagi setelah urutan, hotel, atau moda berubah. */
    private function clearRoutes(TripPlanDay $day): void
    {
        $day->items()->update(['route_distance_m' => null, 'route_duration_s' => null, 'route_calculated_at' => null]);
    }
}

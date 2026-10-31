<?php

namespace App\Planner;

use App\Content\PlaceSearch;
use App\Enums\PriceBasis;
use App\Models\Hotel;
use App\Models\Region;
use App\Models\TripPlan;
use App\Models\TripPlanDay;
use App\Services\Maps\RouteQuery;
use App\Services\Maps\TravelMode;
use App\Services\Planner\Candidate;
use App\Services\Planner\CandidatePool;
use App\Services\Planner\PlannerPreferences;
use App\Services\Planner\RecommendationEngine;
use App\Support\MapMarkers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Menyiapkan data editor: timeline, peta per hari, cadangan, hotel, pencarian katalog,
 * dan ringkasan teks. Peta dan daftar dibangun dari urutan data yang sama (FILE 10).
 */
final class PlanPresenter
{
    public function __construct(
        private readonly RecommendationEngine $engine,
        private readonly CandidatePool $pool,
        private readonly ItineraryEditor $editor,
    ) {}

    public function present(TripPlan $plan, Request $request): array
    {
        $plan->load([
            'days.items.place.region', 'days.items.place.categories',
            'days.hotel', 'days.region', 'originRegion',
        ]);

        $placeIds = $plan->days->flatMap(fn (TripPlanDay $d) => $d->items->pluck('place_id'))->map(fn ($id) => (int) $id)->all();
        $mode = TravelMode::fromTransport($plan->transport_mode);

        $activeDay = (int) $request->query('hari', 0);
        if (! $plan->days->contains('day_number', $activeDay)) {
            $activeDay = $plan->days->first(fn (TripPlanDay $d) => $d->items->isNotEmpty())?->day_number
                ?? $plan->days->first()?->day_number ?? 1;
        }

        return [
            'plan' => $plan,
            'mode' => $mode,
            'activeDay' => $activeDay,
            'mapDays' => $plan->days->mapWithKeys(fn (TripPlanDay $d) => [$d->day_number => $this->mapDay($d, $mode)])->all(),
            'mapsEnabled' => MapMarkers::enabled(),
            'alternatives' => $plan->canRecommend() ? $this->alternatives($plan, $placeIds) : [],
            'hotelOptions' => $this->hotelOptions($plan),
            'stayMatches' => $this->stayMatches($plan),
            'search' => $this->search($request, $placeIds, $plan),
            'searchRegions' => Region::query()->published()->whereHas('places', fn (Builder $q) => $q->published())->orderBy('name')->pluck('name', 'slug')->all(),
            'summary' => $this->summary($plan),
            'maxItems' => (int) config('planner.max_items_per_day'),
            'maxDays' => (int) config('planner.max_days'),
            'editorUrl' => PlanAccess::editorUrl($plan),
        ];
    }

    /** Marker D1·1 dan H, plus rute satu hari. Rute null bila belum bisa dihitung jujur. */
    private function mapDay(TripPlanDay $day, ?TravelMode $mode): array
    {
        $n = $day->day_number;
        $markers = [];
        $stops = [];

        $hotel = $day->hotel?->isPublished() ? $day->hotel : null;
        if ($hotel) {
            $markers[] = MapMarkers::forHotel($hotel)[0];
        }

        foreach ($day->items as $i => $item) {
            $label = 'D'.$n.'·'.($i + 1);
            $place = $item->place;
            $markers[] = [
                'id' => 'item-'.$item->id,
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
                'title' => $label.' '.$place->name,
                'label' => $label,
                'href' => $place->isPublished() ? route('places.show', $place->slug) : null,
                'kind' => 'place',
                'focusId' => 'plan-item-'.$item->id,
            ];
            $stops[] = ['lat' => (float) $place->lat, 'lng' => (float) $place->lng, 'label' => $label.' '.$place->name];
        }

        $origin = $hotel ? ['lat' => (float) $hotel->lat, 'lng' => (float) $hotel->lng, 'label' => 'H '.$hotel->name] : null;

        [$route, $note] = match (true) {
            $stops === [] => [null, 'Belum ada tempat pada hari ini.'],
            $mode === null => [null, 'Transportasi umum tidak dirutekan karena jadwal dan jalurnya belum terverifikasi. Urutan tempat tetap tersimpan.'],
            $origin === null && count($stops) < 2 => [null, 'Rute membutuhkan minimal dua titik. Tambahkan tempat atau atur penginapan sebagai titik awal.'],
            count($stops) - ($origin && ! $day->return_to_hotel ? 1 : 0) - ($origin ? 0 : 2) > RouteQuery::MAX_INTERMEDIATES => [null, 'Terlalu banyak pemberhentian untuk satu rute. Kurangi tempat pada hari ini.'],
            default => [[
                'origin' => $origin,
                'stops' => $stops,
                'returnToOrigin' => (bool) ($origin && $day->return_to_hotel),
                'mode' => $mode->value,
                'active' => true,
            ], null],
        };

        return [
            'label' => 'Hari '.$n,
            'markers' => $markers,
            'route' => $route,
            'routeNote' => $note,
        ];
    }

    /** @return array<int, array<int, array{candidate: Candidate, reason: string}>> */
    private function alternatives(TripPlan $plan, array $placeIds): array
    {
        $prefs = PlannerPreferences::fromArray($plan->planner_answers);
        $out = [];

        foreach ($plan->days as $day) {
            $near = $this->editor->dayCentroid($day) ?? $this->pool->regionCentroid($day->region_id);
            $out[$day->day_number] = $this->engine->alternatives($prefs, $placeIds, $near, 4);
        }

        return $out;
    }

    /** Pilihan penginapan untuk titik awal rute: wilayah arsip dulu, lalu yang sudah dipilih. */
    private function hotelOptions(TripPlan $plan): Collection
    {
        $regions = $plan->days->pluck('region_id')->push($plan->origin_region_id)->filter()->unique()->values();
        $selected = $plan->days->pluck('hotel_id')->filter()->unique();

        return Hotel::query()->published()
            ->whereNotNull('lat')->whereNotNull('lng')
            ->where(fn (Builder $q) => $q
                ->when($regions->isNotEmpty(), fn (Builder $w) => $w->whereIn('region_id', $regions))
                ->orWhereIn('id', $selected))
            ->with('region')
            ->orderBy('name')
            ->limit(80)
            ->get();
    }

    /** Stay matches (FILE 09): terpisah dari itinerary dan tidak pernah dijumlahkan ke biaya apa pun. */
    private function stayMatches(TripPlan $plan): Collection
    {
        $a = $plan->planner_answers;
        if (! is_array($a) || empty($a['want_hotel'])) {
            return collect();
        }

        $regions = array_filter((array) ($a['stay_region_ids'] ?? []));
        if ($regions === []) {
            $regions = $plan->days->pluck('region_id')->filter()->unique()->all();
        }
        $min = $a['price_min'] ?? null;
        $max = $a['price_max'] ?? null;

        return Hotel::query()->published()
            ->with(['region', 'media'])
            ->when($regions !== [], fn (Builder $q) => $q->whereIn('region_id', $regions))
            ->when(! empty($a['hotel_types']), fn (Builder $q) => $q->whereIn('type', $a['hotel_types']))
            ->when($min !== null || $max !== null, function (Builder $q) use ($min, $max) {
                $q->whereNotNull('price_min');
                if ($max !== null) {
                    $q->where('price_min', '<=', $max);
                }
                if ($min !== null) {
                    $q->whereRaw('coalesce(price_max, price_min) >= ?', [$min]);
                }
            })
            ->orderByRaw('case when price_basis = ? then 0 else 1 end', [PriceBasis::Curated->value])
            ->orderBy('name')
            ->limit(4)
            ->get();
    }

    /** Pencarian katalog terbit di dalam editor (mode Manual dan tambah tempat). */
    private function search(Request $request, array $placeIds, TripPlan $plan): ?array
    {
        $q = trim((string) $request->query('cari', ''));
        $region = (string) $request->query('wilayah', '');

        if ($q === '' && $region === '') {
            return null;
        }

        $q = mb_substr($q, 0, 100);
        $results = (new PlaceSearch(['q' => $q ?: null, 'region' => $region ?: null, 'sort' => 'nama']))
            ->query()->whereNotNull('lat')->whereNotNull('lng')->limit(13)->get();

        $dayOf = [];
        foreach ($plan->days as $day) {
            foreach ($day->items as $item) {
                $dayOf[(int) $item->place_id] = $day->day_number;
            }
        }

        return [
            'q' => $q,
            'region' => $region,
            'results' => $results->take(12),
            'more' => $results->count() > 12,
            'inPlan' => $dayOf,
        ];
    }

    /** Ringkasan teks untuk tombol salin (open decision FILE 21: copy summary). */
    private function summary(TripPlan $plan): string
    {
        $lines = [$plan->title];
        foreach ($plan->days as $day) {
            $lines[] = '';
            $lines[] = 'Hari '.$day->day_number.($day->date ? ', '.$day->date->translatedFormat('l j F Y') : '').($day->title ? ': '.$day->title : '');
            if ($day->hotel) {
                $lines[] = '  Penginapan: '.$day->hotel->name;
            }
            foreach ($day->items as $i => $item) {
                $lines[] = '  '.($i + 1).'. '.$item->place->name.($item->place->region ? ' ('.$item->place->region->name.')' : '')
                    .($item->start_time ? ', mulai '.substr((string) $item->start_time, 0, 5) : '')
                    .($item->note ? ' - '.$item->note : '');
            }
            if ($day->items->isEmpty()) {
                $lines[] = '  (belum ada tempat)';
            }
        }
        $lines[] = '';
        $lines[] = 'Disusun di The Dewata Archive. Cek jam buka dan kondisi rute sebelum berangkat.';

        return implode("\n", $lines);
    }
}

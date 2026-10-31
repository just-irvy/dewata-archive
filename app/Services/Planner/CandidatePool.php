<?php

namespace App\Services\Planner;

use App\Models\Place;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;

/**
 * Langkah 1 dan 2 FILE 09: tempat terbit dengan lokasi valid di wilayah terbit, lalu filter
 * keras (wilayah, tema, aksesibilitas). Aksesibilitas hanya dihitung bila terverifikasi.
 */
final class CandidatePool
{
    /** @var array<int, array{lat: float, lng: float}>|null */
    private ?array $centroids = null;

    /**
     * @return array{candidates: array<int, Candidate>, notes: array<int, string>}
     */
    public function for(PlannerPreferences $p): array
    {
        $notes = [];

        $places = Place::query()->published()
            ->whereNotNull('lat')->whereNotNull('lng')
            ->whereHas('region', fn (Builder $r) => $r->published())
            ->when($p->regionIds !== [], fn (Builder $q) => $q->whereIn('region_id', $p->regionIds))
            ->with(['region', 'tags', 'categories'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Place $place) => Geo::validPoint($place->lat, $place->lng));

        $candidates = $places->map(fn (Place $place) => Candidate::fromPlace($place))->values()->all();

        if ($p->mobility !== []) {
            $before = count($candidates);
            $candidates = array_values(array_filter(
                $candidates,
                fn (Candidate $c) => array_reduce($p->mobility, fn ($ok, $key) => $ok && $c->hasAccess($key), true),
            ));
            if (count($candidates) < $before) {
                $notes[] = 'Kebutuhan mobilitas hanya dicocokkan dengan tempat yang data aksesibilitasnya sudah terverifikasi. Tempat lain mungkin cocok tetapi belum diperiksa.';
            }
        }

        if ($p->usesThemes()) {
            $candidates = array_values(array_filter(
                $candidates,
                fn (Candidate $c) => array_intersect($c->tagSlugs, $p->themes) !== [],
            ));
        }

        return ['candidates' => $candidates, 'notes' => $notes];
    }

    /** Titik tengah tempat terbit pada satu wilayah. Dipakai sebagai titik awal kasar. */
    public function regionCentroid(?int $regionId): ?array
    {
        if ($regionId === null) {
            return null;
        }

        if ($this->centroids === null) {
            $this->centroids = [];
            $rows = Place::query()->published()
                ->whereNotNull('lat')->whereNotNull('lng')
                ->selectRaw('region_id, avg(lat) as lat, avg(lng) as lng')
                ->groupBy('region_id')
                ->get();

            foreach ($rows as $row) {
                $this->centroids[(int) $row->region_id] = ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
            }
        }

        return $this->centroids[$regionId] ?? null;
    }

    public function regionName(int $regionId): string
    {
        return (string) Region::query()->whereKey($regionId)->value('name');
    }
}

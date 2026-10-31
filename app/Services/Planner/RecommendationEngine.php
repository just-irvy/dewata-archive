<?php

namespace App\Services\Planner;

use App\Support\Words;

/**
 * Rekomendasi berbasis aturan (FILE 09). Deterministik: masukan yang sama dan katalog yang
 * sama selalu menghasilkan itinerary yang sama, sehingga bisa dites dan ditelusuri.
 *
 * Urutan: kandidat terfilter, skor berbobot, pembagian hari per wilayah, pengelompokan
 * tempat berdekatan dalam satu hari, batas harian sesuai pace, lalu pemeriksaan akhir.
 * Jarak garis lurus dipakai hanya untuk peringkat. Tidak pernah dikembalikan sebagai jarak.
 */
final class RecommendationEngine
{
    public function __construct(private readonly CandidatePool $pool) {}

    public function build(PlannerPreferences $p): DraftItinerary
    {
        ['candidates' => $candidates, 'notes' => $notes] = $this->pool->for($p);

        if ($candidates === []) {
            return new DraftItinerary([], array_merge($notes, [$this->emptyMessage($p)]));
        }

        $anchor = $this->pool->regionCentroid($p->originRegionId);
        $this->score($candidates, $p, $anchor);

        [$sequence, $regionNotes] = $this->planRegions($candidates, $p, $anchor);
        $notes = array_merge($notes, $regionNotes);

        $used = [];
        $days = [];
        foreach ($sequence as $regionId) {
            $day = $this->fillDay($candidates, $p, $regionId, $used, $anchor);
            $used = array_merge($used, $day->placeIds());
            $days[] = $day;
        }

        return new DraftItinerary($days, array_merge($notes, $this->finalChecks($days, $p)));
    }

    /**
     * Menyusun ulang satu hari (FILE 09, edit tools: regenerate hari terpilih).
     *
     * @param  array<int, int>  $excludeIds  tempat yang sudah ada di hari lain
     */
    public function rebuildDay(PlannerPreferences $p, ?int $regionId, array $excludeIds): DraftDay
    {
        ['candidates' => $candidates] = $this->pool->for($p);
        $anchor = $this->pool->regionCentroid($p->originRegionId);
        $this->score($candidates, $p, $anchor);

        $available = array_filter($candidates, fn (Candidate $c) => ! in_array($c->id, $excludeIds, true));
        $hasRegion = $regionId !== null && array_filter($available, fn (Candidate $c) => $c->regionId === $regionId) !== [];

        if (! $hasRegion) {
            $regionId = $this->bestRegion($available);
        }

        return $regionId === null
            ? new DraftDay(null, null, [])
            : $this->fillDay($candidates, $p, $regionId, $excludeIds, $anchor);
    }

    /**
     * Cadangan dan pengganti: tempat yang cocok, belum dipakai, dan dekat dengan hari itu.
     *
     * @param  array<int, int>  $excludeIds
     * @param  array{lat: float, lng: float}|null  $near
     * @return array<int, array{candidate: Candidate, reason: string}>
     */
    public function alternatives(PlannerPreferences $p, array $excludeIds, ?array $near, int $limit = 5): array
    {
        ['candidates' => $candidates] = $this->pool->for($p);
        $this->score($candidates, $p, $this->pool->regionCentroid($p->originRegionId));

        $radius = $p->radiusKm();
        $w = config('planner.weights');
        $ranked = [];

        foreach ($candidates as $c) {
            if (in_array($c->id, $excludeIds, true)) {
                continue;
            }
            $value = $c->score;
            if ($near !== null) {
                $d = $c->km($near);
                if ($d > $radius * 1.5) {
                    continue;
                }
                $value -= $w['cluster'] * ($d / $radius);
            }
            $ranked[] = [$value, $c];
        }

        usort($ranked, fn ($a, $b) => [$b[0], $a[1]->id] <=> [$a[0], $b[1]->id]);

        return array_map(
            fn ($row) => ['candidate' => $row[1], 'reason' => $this->reason($row[1], [], $p)],
            array_slice($ranked, 0, $limit),
        );
    }

    /** @param  array<int, Candidate>  $candidates */
    private function score(array $candidates, PlannerPreferences $p, ?array $anchor): void
    {
        $w = config('planner.weights');
        $outdoor = config('planner.outdoor_tags');
        $radius = $p->radiusKm();

        foreach ($candidates as $c) {
            $score = 0.0;
            $c->matchedThemes = [];

            if ($p->usesThemes()) {
                $matched = array_values(array_intersect($p->themes, $c->tagSlugs));
                $c->matchedThemes = array_map(fn ($slug) => $c->tagNames[$slug] ?? $slug, $matched);
                $score += $w['theme'] * count($matched) / max(1, count($p->themes));
            }

            if ($anchor !== null) {
                $score += $w['proximity'] * max(0.0, 1 - $c->km($anchor) / ($radius * 3));
            }

            if ($c->verified) {
                $score += $w['verified'];
            }
            if ($c->featured) {
                $score += $w['featured'];
            }
            if ($p->avoidOutdoor && array_intersect($c->tagSlugs, $outdoor) !== []) {
                $score -= $w['outdoor'];
            }

            $c->score = round($score, 6);
        }
    }

    /**
     * Membagi hari ke wilayah. Hari di wilayah yang sama diletakkan berurutan, dan urutan
     * wilayah mengikuti tetangga terdekat dari titik awal, agar tidak bolak-balik (FILE 07).
     *
     * @param  array<int, Candidate>  $candidates
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    private function planRegions(array $candidates, PlannerPreferences $p, ?array $anchor): array
    {
        $notes = [];
        $maxStops = $p->maxStops();

        $groups = [];
        foreach ($candidates as $c) {
            $groups[$c->regionId][] = $c;
        }

        // Tanpa pilihan wilayah dan dengan titik awal, "nearby" berarti tetap dekat titik awal.
        if ($p->regionIds === [] && $anchor !== null) {
            $limit = $p->radiusKm() * ($p->flexibility === 'nearby' ? 2 : 4);
            $near = array_filter($groups, fn ($list) => $this->distance(Geo::centroid($list), $anchor) <= $limit);
            if ($near !== []) {
                $groups = $near;
            }
        }

        foreach ($p->regionIds as $regionId) {
            if (! isset($groups[$regionId])) {
                $notes[] = 'Tidak ada tempat yang cocok dengan pilihan Anda di '.$this->pool->regionName($regionId).'.';
            }
        }

        // Kualitas wilayah: jumlah skor tempat terbaik sebanyak kapasitas satu hari.
        $quality = [];
        foreach ($groups as $regionId => $list) {
            $scores = array_map(fn (Candidate $c) => $c->score, $list);
            rsort($scores);
            $quality[$regionId] = array_sum(array_slice($scores, 0, $maxStops)) + count($list) * 0.01;
        }
        uksort($quality, fn ($a, $b) => [$quality[$b], $a] <=> [$quality[$a], $b]);

        $chosen = array_slice(array_keys($quality), 0, $p->daysCount);
        if (count($quality) > $p->daysCount && $p->regionIds !== []) {
            $notes[] = 'Wilayah yang dipilih lebih banyak dari jumlah hari. Sebagian wilayah tidak masuk agar tiap hari tidak berpindah jauh.';
        }

        // Satu hari per wilayah, sisa hari ke wilayah yang masih punya tempat.
        $days = array_fill_keys($chosen, 1);
        $remaining = $p->daysCount - count($chosen);
        while ($remaining > 0) {
            $best = null;
            $bestSpare = PHP_INT_MIN;
            foreach ($chosen as $regionId) {
                $spare = count($groups[$regionId]) - $days[$regionId] * $maxStops;
                if ($spare > $bestSpare) {
                    $bestSpare = $spare;
                    $best = $regionId;
                }
            }
            $days[$best]++;
            $remaining--;
        }

        // Urutan wilayah: tetangga terdekat mulai dari titik awal atau wilayah terbaik.
        $centroids = array_map(fn ($regionId) => Geo::centroid($groups[$regionId]), array_combine($chosen, $chosen));
        $order = [];
        $current = $anchor;
        $left = $chosen;
        while ($left !== []) {
            if ($current === null) {
                $next = $left[0];
            } else {
                usort($left, fn ($a, $b) => [$this->distance($centroids[$a], $current), $a] <=> [$this->distance($centroids[$b], $current), $b]);
                $next = $left[0];
            }
            $order[] = $next;
            $current = $centroids[$next];
            $left = array_values(array_diff($left, [$next]));
        }

        $sequence = [];
        foreach ($order as $regionId) {
            for ($i = 0; $i < $days[$regionId]; $i++) {
                $sequence[] = $regionId;
            }
        }

        if (count($order) > 1 && $p->daysCount <= 2) {
            $notes[] = 'Durasi pendek dengan lebih dari satu wilayah berarti waktu di jalan lebih banyak. Pertimbangkan satu wilayah saja.';
        }

        return [$sequence, $notes];
    }

    /**
     * @param  array<int, Candidate>  $candidates
     * @param  array<int, int>  $used
     */
    private function fillDay(array $candidates, PlannerPreferences $p, int $regionId, array $used, ?array $anchor): DraftDay
    {
        $w = config('planner.weights');
        $radius = $p->radiusKm();
        $maxStops = $p->maxStops();
        $budget = $p->minuteBudget();
        $buffer = (int) config('planner.buffer_minutes');

        $pool = array_values(array_filter(
            $candidates,
            fn (Candidate $c) => $c->regionId === $regionId && ! in_array($c->id, $used, true),
        ));

        if ($pool === []) {
            return new DraftDay($regionId, $this->pool->regionName($regionId), []);
        }

        usort($pool, fn (Candidate $a, Candidate $b) => [$b->score, $a->id] <=> [$a->score, $b->id]);
        $seed = $pool[0];
        $chosen = [$seed];
        $minutes = $seed->minutes();
        $categoryCount = array_count_values(array_filter([$seed->primaryCategory()]));

        while (count($chosen) < $maxStops) {
            $last = end($chosen);
            $best = null;
            $bestValue = -INF;

            foreach ($pool as $c) {
                if (in_array($c, $chosen, true) || $c->km($seed) > $radius) {
                    continue;
                }
                if ($minutes + $buffer + $c->minutes() > $budget) {
                    continue;
                }
                $value = $c->score
                    - $w['cluster'] * ($c->km($last) / $radius)
                    - $w['variety'] * ($categoryCount[$c->primaryCategory()] ?? 0);

                if ($value > $bestValue || ($value === $bestValue && $c->id < $best->id)) {
                    $bestValue = $value;
                    $best = $c;
                }
            }

            if ($best === null) {
                break;
            }

            $chosen[] = $best;
            $minutes += $buffer + $best->minutes();
            if ($best->primaryCategory() !== null) {
                $categoryCount[$best->primaryCategory()] = ($categoryCount[$best->primaryCategory()] ?? 0) + 1;
            }
        }

        $ordered = $this->orderStops($chosen, $anchor);

        $items = array_map(fn (Candidate $c) => ['candidate' => $c, 'reason' => $this->reason($c, $ordered, $p)], $ordered);

        return new DraftDay($regionId, $this->dayTitle($ordered), $items);
    }

    /**
     * Tetangga terdekat, mulai dari tempat yang paling dekat dengan titik awal.
     *
     * @param  array<int, Candidate>  $stops
     * @return array<int, Candidate>
     */
    private function orderStops(array $stops, ?array $anchor): array
    {
        if (count($stops) < 2) {
            return $stops;
        }

        $start = $anchor ?? $stops[0]->point();
        $left = $stops;
        $ordered = [];
        $current = $start;

        while ($left !== []) {
            usort($left, fn (Candidate $a, Candidate $b) => [$a->km($current), $a->id] <=> [$b->km($current), $b->id]);
            $next = array_shift($left);
            $ordered[] = $next;
            $current = $next->point();
        }

        return $ordered;
    }

    /**
     * Alasan singkat yang bisa ditelusuri ke atribut data. Bukan rantai pemikiran.
     *
     * @param  array<int, Candidate>  $day
     */
    public function reason(Candidate $c, array $day, PlannerPreferences $p): string
    {
        $parts = [];

        if ($c->matchedThemes !== []) {
            $parts[] = 'cocok dengan tema '.Words::join($c->matchedThemes);
        }

        $others = array_filter($day, fn (Candidate $o) => $o->id !== $c->id && (
            $c->areaLabel !== null ? $o->areaLabel === $c->areaLabel : $o->regionId === $c->regionId
        ));
        if ($others !== []) {
            $parts[] = 'berada di '.($c->areaLabel ?: $c->regionName).' bersama '.count($others).' tempat lain hari ini';
        }

        if ($p->mobility !== []) {
            $parts[] = 'data aksesibilitasnya terverifikasi sesuai kebutuhan mobilitas';
        }

        if ($parts === []) {
            $parts[] = 'menambah variasi di '.($c->areaLabel ?: $c->regionName);
        }

        return mb_substr('Dipilih karena '.Words::join($parts).'.', 0, 300);
    }

    /** @param  array<int, Candidate>  $stops */
    private function dayTitle(array $stops): string
    {
        $region = $stops[0]->regionName;
        $areas = array_count_values(array_filter(array_map(fn (Candidate $c) => $c->areaLabel, $stops)));
        arsort($areas);
        $top = array_key_first($areas);

        if ($top !== null && count($stops) <= $areas[$top] * 2 && $top !== $region) {
            return $top.' dan sekitarnya, '.$region;
        }

        return 'Sekitar '.$region;
    }

    /**
     * Pemeriksaan akhir (FILE 09, langkah 6).
     *
     * @param  array<int, DraftDay>  $days
     * @return array<int, string>
     */
    private function finalChecks(array $days, PlannerPreferences $p): array
    {
        $notes = [];
        $empty = [];
        $thin = 0;
        $unverifiedHours = false;
        $seen = [];

        foreach ($days as $i => $day) {
            if ($day->items === []) {
                $empty[] = $i + 1;
            } elseif (count($day->items) < min(2, $p->maxStops())) {
                $thin++;
            }
            foreach ($day->items as $item) {
                if (isset($seen[$item['candidate']->id])) {
                    throw new \LogicException('Tempat ganda dalam rekomendasi.');
                }
                $seen[$item['candidate']->id] = true;
                $unverifiedHours = $unverifiedHours || ! $item['candidate']->hoursVerified;
            }
        }

        if ($empty !== []) {
            $notes[] = 'Hari '.Words::join(array_map('strval', $empty)).' belum terisi karena tempat yang cocok sudah habis. Tambahkan tempat sendiri, atau ubah preferensi untuk memperluas wilayah dan tema.';
        }
        if ($thin > 0) {
            $notes[] = 'Beberapa hari berisi lebih sedikit tempat dari kapasitas pace yang dipilih, karena tempat yang cocok di dekatnya terbatas. Tempat tidak digandakan untuk mengisi kekosongan.';
        }
        if ($unverifiedHours) {
            $notes[] = 'Jam buka sebagian tempat belum terverifikasi. Cek jam buka sebelum berkunjung.';
        }

        return $notes;
    }

    /** @param  array<int, Candidate>  $candidates */
    private function bestRegion(array $candidates): ?int
    {
        $sum = [];
        foreach ($candidates as $c) {
            $sum[$c->regionId] = ($sum[$c->regionId] ?? 0) + $c->score + 0.01;
        }
        if ($sum === []) {
            return null;
        }
        uksort($sum, fn ($a, $b) => [$sum[$b], $a] <=> [$sum[$a], $b]);

        return array_key_first($sum);
    }

    private function emptyMessage(PlannerPreferences $p): string
    {
        return $p->usesThemes() || $p->regionIds !== [] || $p->mobility !== []
            ? 'Belum ada tempat terbit yang cocok dengan kombinasi wilayah, tema, dan kebutuhan mobilitas ini. Coba tambah wilayah, kurangi tema, atau pilih "Kejutkan saya".'
            : 'Belum ada tempat terbit dengan lokasi yang valid di arsip.';
    }

    private function distance(array $a, array $b): float
    {
        return Geo::km($a['lat'], $a['lng'], $b['lat'], $b['lng']);
    }
}

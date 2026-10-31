<?php

namespace App\Planner;

use App\Enums\CreationMode;
use App\Enums\ItemSource;
use App\Models\TripPlan;
use App\Models\User;
use App\Services\Planner\DraftItinerary;
use App\Services\Planner\PlannerPreferences;
use App\Services\Planner\RecommendationEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dua cara memulai satu arsip (FILE 10). Keduanya menulis ke trip_plans, trip_plan_days,
 * dan trip_plan_items yang sama. creation_mode hanya provenance, bukan jenis arsip.
 */
final class PlanFactory
{
    public function __construct(
        private readonly RecommendationEngine $engine,
        private readonly GuestDrafts $drafts,
    ) {}

    /**
     * Mode Rekomendasi. Mengembalikan null bila tidak ada satu pun tempat yang cocok, agar
     * pengguna tidak dibawa ke arsip kosong yang tampak seperti hasil.
     *
     * @return array{plan: ?TripPlan, draft: DraftItinerary}
     */
    public function recommended(PlannerPreferences $p, ?User $user, ?string $title = null): array
    {
        $draft = $this->engine->build($p);

        if ($draft->isEmpty()) {
            return ['plan' => null, 'draft' => $draft];
        }

        $plan = DB::transaction(function () use ($p, $user, $title, $draft) {
            $plan = $this->newPlan($user, [
                'title' => $title ?: $this->defaultTitle($p->daysCount, $draft),
                'creation_mode' => CreationMode::Recommended,
                'start_date' => $p->startDate,
                'days_count' => $p->daysCount,
                'origin_region_id' => $p->originRegionId,
                'pace' => $p->pace,
                'transport_mode' => $p->transport,
                'planner_answers' => $p->toArray(),
                'recommendation_version' => config('planner.recommendation_version'),
                'generated_at' => now(),
                'generation_notes' => $draft->notes,
            ]);

            foreach (range(1, $p->daysCount) as $n) {
                $draftDay = $draft->days[$n - 1] ?? null;
                $day = $plan->days()->create([
                    'day_number' => $n,
                    'sort_order' => $n,
                    'date' => $this->dateFor($p->startDate, $n),
                    'region_id' => $draftDay?->regionId,
                    'title' => $draftDay?->title,
                ]);

                foreach ($draftDay?->items ?? [] as $i => $item) {
                    $day->items()->create([
                        'place_id' => $item['candidate']->id,
                        'item_source' => ItemSource::Recommended,
                        'recommendation_reason' => $item['reason'],
                        'duration_minutes' => $item['candidate']->visitMinutes,
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            return $plan;
        });

        return ['plan' => $plan, 'draft' => $draft];
    }

    /**
     * Mode Manual: arsip kosong. Tidak ada tempat yang disisipkan otomatis.
     *
     * @param  array{title: string, days_count: int, start_date?: ?string, origin_region_id?: ?int, transport_mode?: ?string}  $data
     */
    public function manual(array $data, ?User $user): TripPlan
    {
        return DB::transaction(function () use ($data, $user) {
            $plan = $this->newPlan($user, [
                'title' => $data['title'],
                'creation_mode' => CreationMode::Manual,
                'start_date' => $data['start_date'] ?? null,
                'days_count' => $data['days_count'],
                'origin_region_id' => $data['origin_region_id'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
            ]);

            foreach (range(1, (int) $data['days_count']) as $n) {
                $plan->days()->create([
                    'day_number' => $n,
                    'sort_order' => $n,
                    'date' => $this->dateFor($data['start_date'] ?? null, $n),
                ]);
            }

            return $plan;
        });
    }

    private function newPlan(?User $user, array $attributes): TripPlan
    {
        if ($user !== null) {
            return $user->tripPlans()->create($attributes);
        }

        $plan = TripPlan::query()->create($attributes);
        $this->drafts->issue($plan);

        return $plan;
    }

    public function dateFor(?string $start, int $dayNumber): ?string
    {
        return $start ? Carbon::parse($start)->addDays($dayNumber - 1)->toDateString() : null;
    }

    private function defaultTitle(int $days, DraftItinerary $draft): string
    {
        $regions = collect($draft->days)->pluck('regionId')->filter()->unique()->count();

        return $days.' hari di Bali'.($regions === 1 ? ', '.$this->firstRegionName($draft) : '');
    }

    private function firstRegionName(DraftItinerary $draft): string
    {
        foreach ($draft->days as $day) {
            if ($day->items !== []) {
                return $day->items[0]['candidate']->regionName;
            }
        }

        return '';
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use App\Services\Planner\PlannerPreferences;
use App\Services\Planner\RecommendationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPlannerData;
use Tests\TestCase;

/** Mesin rekomendasi berbasis aturan (FILE 09). Tanpa AI, deterministik, dan bisa dites. */
class RecommendationEngineTest extends TestCase
{
    use BuildsPlannerData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    private function build(array $answers)
    {
        return app(RecommendationEngine::class)->build(PlannerPreferences::fromArray($this->wizard($answers)));
    }

    public function test_only_published_places_with_valid_coordinates_are_used(): void
    {
        $draft = $this->spot($this->gianyar, 'Pura Draf', -8.42, 115.31, [$this->pura], [], PublishStatus::Draft);
        $broken = $this->spot($this->gianyar, 'Pura Tanpa Lokasi', 0, 0, [$this->pura]);

        $ids = $this->build(['days_count' => 3, 'region_ids' => [$this->gianyar->id], 'pace' => 'packed'])->placeIds();

        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($broken->id, $ids);
        $this->assertNotEmpty($ids);
    }

    public function test_themes_are_a_hard_filter_and_surprise_ignores_them(): void
    {
        $result = $this->build(['themes' => ['alam'], 'days_count' => 3, 'pace' => 'packed']);
        foreach ($result->days as $day) {
            foreach ($day->items as $item) {
                $this->assertContains('alam', $item['candidate']->tagSlugs);
            }
        }

        $surprise = $this->build(['themes' => [], 'surprise' => true, 'days_count' => 3, 'pace' => 'packed']);
        $this->assertGreaterThan(count($result->placeIds()), count($surprise->placeIds()));
    }

    public function test_days_respect_pace_and_never_repeat_a_place(): void
    {
        $result = $this->build(['days_count' => 3, 'pace' => 'relaxed']);

        $max = (int) config('planner.pace.relaxed.max_stops');
        foreach ($result->days as $day) {
            $this->assertLessThanOrEqual($max, count($day->items));
        }

        $ids = $result->placeIds();
        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    public function test_each_day_stays_in_one_region_instead_of_crossing_the_island(): void
    {
        $result = $this->build(['days_count' => 2, 'region_ids' => [$this->gianyar->id, $this->buleleng->id], 'pace' => 'packed']);

        $this->assertCount(2, $result->days);
        foreach ($result->days as $day) {
            $regions = array_unique(array_map(fn ($i) => $i['candidate']->regionId, $day->items));
            $this->assertCount(1, $regions);
        }
        $this->assertNotSame($result->days[0]->regionId, $result->days[1]->regionId);
    }

    public function test_origin_region_comes_first(): void
    {
        $result = $this->build([
            'days_count' => 2,
            'region_ids' => [$this->gianyar->id, $this->buleleng->id],
            'origin_region_id' => $this->buleleng->id,
        ]);

        $this->assertSame($this->buleleng->id, $result->days[0]->regionId);
    }

    public function test_shortage_is_explained_and_never_filled_with_duplicates(): void
    {
        $result = $this->build(['days_count' => 5, 'region_ids' => [$this->buleleng->id], 'themes' => ['pura-arsitektur'], 'pace' => 'packed']);

        $ids = $result->placeIds();
        $this->assertCount(2, $ids);
        $this->assertSame($ids, array_unique($ids));
        $this->assertCount(5, $result->days);
        $this->assertTrue(collect($result->notes)->contains(fn ($n) => str_contains($n, 'belum terisi')));
    }

    public function test_nothing_matching_returns_an_empty_itinerary_with_a_reason(): void
    {
        $result = $this->build(['themes' => ['pura-arsitektur'], 'mobility' => ['step_free']]);

        $this->assertTrue($result->isEmpty());
        $this->assertNotEmpty($result->notes);
    }

    public function test_mobility_only_counts_verified_accessibility(): void
    {
        $verified = $this->spot($this->gianyar, 'Museum Datar', -8.51, 115.26, [$this->pura], [
            'accessibility' => ['step_free' => 'yes'], 'accessibility_status' => VerificationStatus::Verified,
        ]);
        $guessed = $this->spot($this->gianyar, 'Museum Tebakan', -8.51, 115.27, [$this->pura], [
            'accessibility' => ['step_free' => 'yes'], 'accessibility_status' => VerificationStatus::NeedsReview,
        ]);

        $ids = $this->build(['days_count' => 1, 'mobility' => ['step_free']])->placeIds();

        $this->assertSame([$verified->id], $ids);
        $this->assertNotContains($guessed->id, $ids);
    }

    public function test_reasons_trace_back_to_data(): void
    {
        $result = $this->build(['days_count' => 1, 'themes' => ['pura-arsitektur'], 'region_ids' => [$this->gianyar->id]]);

        $reason = $result->days[0]->items[0]['reason'];
        $this->assertStringStartsWith('Dipilih karena', $reason);
        $this->assertStringContainsString('Pura dan Arsitektur', $reason);
        $this->assertLessThanOrEqual(300, mb_strlen($reason));
    }

    public function test_result_is_deterministic(): void
    {
        $a = $this->build(['days_count' => 2, 'surprise' => true, 'themes' => []]);
        $b = $this->build(['days_count' => 2, 'surprise' => true, 'themes' => []]);

        $this->assertSame($a->placeIds(), $b->placeIds());
    }

    public function test_daily_time_changes_capacity(): void
    {
        $early = PlannerPreferences::fromArray($this->wizard(['pace' => 'balanced', 'daily_time' => 'early']));
        $late = PlannerPreferences::fromArray($this->wizard(['pace' => 'balanced', 'daily_time' => 'not_late']));

        $this->assertSame($early->maxStops(), $late->maxStops() + 2);
        $this->assertLessThanOrEqual(config('planner.max_items_per_day'), PlannerPreferences::fromArray($this->wizard(['pace' => 'packed', 'daily_time' => 'early']))->maxStops());
    }
}

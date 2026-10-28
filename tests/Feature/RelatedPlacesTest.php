<?php

namespace Tests\Feature;

use App\Content\RelatedPlaces;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedPlacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_shared_themes_in_the_same_region_then_fills_from_the_region(): void
    {
        $r1 = Region::factory()->create();
        $r2 = Region::factory()->create();
        $shared = Category::factory()->create();

        $subject = Place::factory()->published()->create(['region_id' => $r1->id]);
        $subject->categories()->attach($shared);

        $sameRegionShared = Place::factory()->published()->create(['region_id' => $r1->id]);
        $sameRegionShared->categories()->attach($shared);

        $otherRegionShared = Place::factory()->published()->create(['region_id' => $r2->id]);
        $otherRegionShared->categories()->attach($shared);

        $fill = Place::factory()->published()->create(['region_id' => $r1->id]);

        $draft = Place::factory()->create(['region_id' => $r1->id]);
        $draft->categories()->attach($shared);

        $ids = (new RelatedPlaces)->for($subject, 3)->pluck('id')->all();

        $this->assertSame([$sameRegionShared->id, $otherRegionShared->id, $fill->id], $ids);
        $this->assertNotContains($subject->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_returns_nothing_when_there_is_nothing_related(): void
    {
        $lonely = Place::factory()->published()->create();

        $this->assertCount(0, (new RelatedPlaces)->for($lonely));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PriceBasis;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class PublishWorkflowTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private function publish(string $type, int $id, string $to = 'published'): TestResponse
    {
        return $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/{$type}/{$id}/status", ['to' => $to]);
    }

    public function test_draft_without_source_cannot_be_published(): void
    {
        $place = Place::factory()->create();

        $this->publish('places', $place->id)->assertSessionHasErrors('publish');

        $this->assertSame(PublishStatus::Draft, $place->fresh()->status);
    }

    public function test_ready_place_is_published_and_audited(): void
    {
        $place = Place::factory()->ready()->create();

        $this->publish('places', $place->id)->assertSessionHasNoErrors();

        $place->refresh();
        $this->assertSame(PublishStatus::Published, $place->status);
        $this->assertNotNull($place->published_at);

        $log = AuditLog::query()->where('action', 'place.published')->firstOrFail();
        $this->assertSame('draft', $log->before_json['status']);
        $this->assertSame('published', $log->after_json['status']);
    }

    public function test_unpublished_region_blocks_publishing(): void
    {
        $place = Place::factory()->ready()->create(['region_id' => Region::factory()->unpublished()]);

        $this->publish('places', $place->id)->assertSessionHasErrors('publish');
    }

    public function test_media_without_alt_text_blocks_publishing(): void
    {
        $place = Place::factory()->ready()->create();
        Media::factory()->withoutAlt()->for($place, 'mediable')->create();

        $this->publish('places', $place->id)->assertSessionHasErrors('publish');
        $this->assertSame(PublishStatus::Draft, $place->fresh()->status);
    }

    public function test_published_place_can_be_archived_and_restored_to_draft(): void
    {
        $place = Place::factory()->published()->create();

        $this->publish('places', $place->id, 'archived');
        $this->assertSame(PublishStatus::Archived, $place->fresh()->status);

        $this->publish('places', $place->id, 'draft');
        $this->assertSame(PublishStatus::Draft, $place->fresh()->status);
    }

    public function test_regular_user_cannot_change_status(): void
    {
        $place = Place::factory()->ready()->create();

        $this->actingAs($this->makeUser())
            ->post("/admin/places/{$place->id}/status", ['to' => 'published'])
            ->assertForbidden();

        $this->assertSame(PublishStatus::Draft, $place->fresh()->status);
    }

    public function test_invalid_target_status_is_rejected(): void
    {
        $place = Place::factory()->ready()->create();

        $this->publish('places', $place->id, 'hacked')->assertSessionHasErrors('to');
    }

    public function test_hotel_with_price_needs_a_verified_price_status(): void
    {
        $hotel = Hotel::factory()->ready()->create(['price_min' => 400000, 'price_basis' => PriceBasis::Curated]);

        $this->publish('hotels', $hotel->id)->assertSessionHasErrors('publish');

        $hotel->forceFill(['price_status' => 'verified'])->save();

        $this->publish('hotels', $hotel->id)->assertSessionHasNoErrors();
        $this->assertSame(PublishStatus::Published, $hotel->fresh()->status);
    }

    public function test_hotel_without_price_can_be_published_with_a_warning_only(): void
    {
        $hotel = Hotel::factory()->ready()->create();

        $this->publish('hotels', $hotel->id)->assertSessionHasNoErrors();
        $this->assertSame(PublishStatus::Published, $hotel->fresh()->status);
    }

    public function test_collection_needs_published_places(): void
    {
        $collection = Collection::factory()->create();
        $this->publish('collections', $collection->id)->assertSessionHasErrors('publish');

        $collection->places()->attach(Place::factory()->create());
        $this->publish('collections', $collection->id)->assertSessionHasErrors('publish');

        $collection->places()->detach();
        $collection->places()->attach(Place::factory()->published()->create());
        $this->publish('collections', $collection->id)->assertSessionHasNoErrors();
        $this->assertSame(PublishStatus::Published, $collection->fresh()->status);
    }
}

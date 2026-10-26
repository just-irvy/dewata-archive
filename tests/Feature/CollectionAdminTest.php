<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class CollectionAdminTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private function admin()
    {
        return $this->actingAs($this->makeUser(UserRole::Admin));
    }

    public function test_admin_creates_a_collection_and_opens_its_editor(): void
    {
        $this->admin()->post('/admin/collections', ['title' => 'Pagi yang tenang di Ubud', 'dek' => 'Tiga tempat.'])
            ->assertSessionHasNoErrors();

        $collection = Collection::query()->firstOrFail();
        $this->assertSame('pagi-yang-tenang-di-ubud', $collection->slug);

        $this->admin()->get("/admin/collections/{$collection->id}/edit")->assertOk()->assertSee('Pagi yang tenang di Ubud');
    }

    public function test_only_published_places_can_be_added_once(): void
    {
        $collection = Collection::factory()->create();
        $published = Place::factory()->published()->create();
        $draft = Place::factory()->create();

        $this->admin()->post("/admin/collections/{$collection->id}/places", ['place_id' => $draft->id])
            ->assertSessionHasErrors('place_id');

        $this->admin()->post("/admin/collections/{$collection->id}/places", ['place_id' => $published->id])
            ->assertSessionHasNoErrors();

        $this->admin()->post("/admin/collections/{$collection->id}/places", ['place_id' => $published->id])
            ->assertSessionHasErrors('place_id');

        $this->assertSame(1, $collection->places()->count());
    }

    public function test_new_places_go_to_the_end_by_default(): void
    {
        $collection = Collection::factory()->create();
        $a = Place::factory()->published()->create();
        $b = Place::factory()->published()->create();

        $this->admin()->post("/admin/collections/{$collection->id}/places", ['place_id' => $a->id]);
        $this->admin()->post("/admin/collections/{$collection->id}/places", ['place_id' => $b->id]);

        $this->assertSame([$a->id, $b->id], $collection->places()->pluck('places.id')->all());
    }

    public function test_cover_must_come_from_a_place_in_the_collection(): void
    {
        $collection = Collection::factory()->create();
        $inside = Place::factory()->published()->create();
        $outside = Place::factory()->published()->create();
        $collection->places()->attach($inside);

        $insideMedia = Media::factory()->for($inside, 'mediable')->create();
        $outsideMedia = Media::factory()->for($outside, 'mediable')->create();

        $payload = ['title' => $collection->title, 'slug' => $collection->slug];

        $this->admin()->put("/admin/collections/{$collection->id}", $payload + ['cover_media_id' => $outsideMedia->id])
            ->assertSessionHasErrors('cover_media_id');

        $this->admin()->put("/admin/collections/{$collection->id}", $payload + ['cover_media_id' => $insideMedia->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($insideMedia->id, $collection->fresh()->cover_media_id);
    }

    public function test_removing_the_place_clears_a_cover_that_came_from_it(): void
    {
        $collection = Collection::factory()->create();
        $place = Place::factory()->published()->create();
        $media = Media::factory()->for($place, 'mediable')->create();
        $collection->places()->attach($place);
        $collection->forceFill(['cover_media_id' => $media->id])->save();

        $this->admin()->delete("/admin/collections/{$collection->id}/places/{$place->id}");

        $this->assertNull($collection->fresh()->cover_media_id);
    }

    public function test_published_collection_cannot_be_deleted(): void
    {
        $collection = Collection::factory()->create();
        $collection->forceFill(['status' => 'published'])->save();

        $this->admin()->delete("/admin/collections/{$collection->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted($collection);
    }
}

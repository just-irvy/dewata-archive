<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class FeaturedPlaceTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private function save(Place $place, array $extra = [])
    {
        return $this->actingAs($this->makeUser(UserRole::Admin))->put("/admin/places/{$place->id}", $this->placePayload([
            'region_id' => $place->region_id,
            'name' => $place->name,
            'slug' => $place->slug,
            'summary' => $place->summary,
            'lat' => (string) $place->lat,
            'lng' => (string) $place->lng,
        ] + $extra));
    }

    public function test_admin_can_feature_and_unfeature_a_place(): void
    {
        $place = Place::factory()->create();

        $this->save($place, ['is_featured' => '1'])->assertSessionHasNoErrors();
        $this->assertNotNull($place->fresh()->featured_at);

        $this->save($place, ['is_featured' => '0'])->assertSessionHasNoErrors();
        $this->assertNull($place->fresh()->featured_at);
    }

    public function test_saving_again_keeps_the_original_featured_time(): void
    {
        $place = Place::factory()->featured()->create(['featured_at' => now()->subDays(3)]);
        $before = $place->fresh()->featured_at;

        $this->save($place, ['is_featured' => '1']);

        $this->assertTrue($before->equalTo($place->fresh()->featured_at));
    }

    public function test_featured_flag_cannot_be_set_by_a_regular_user(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->makeUser())->put("/admin/places/{$place->id}", ['is_featured' => '1'])->assertForbidden();
        $this->assertNull($place->fresh()->featured_at);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class TaxonomyAdminTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private function admin()
    {
        return $this->actingAs($this->makeUser(UserRole::Admin));
    }

    public function test_admin_creates_a_region_with_generated_slug(): void
    {
        $this->admin()->post('/admin/regions', [
            'name' => 'Kota Denpasar', 'region_type' => 'administrative', 'is_published' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Region::where('slug', 'kota-denpasar')->where('is_published', true)->exists());
    }

    public function test_region_slug_must_be_unique(): void
    {
        Region::factory()->create(['slug' => 'ubud']);

        $this->admin()->post('/admin/regions', ['name' => 'Ubud', 'region_type' => 'editorial', 'is_published' => '0'])
            ->assertSessionHasErrors('slug');
    }

    public function test_region_with_published_places_cannot_be_unpublished(): void
    {
        $place = Place::factory()->published()->create();

        $this->admin()->put("/admin/regions/{$place->region_id}", [
            'name' => $place->region->name, 'slug' => $place->region->slug,
            'region_type' => 'administrative', 'is_published' => '0',
        ])->assertSessionHasErrors('is_published');

        $this->assertTrue($place->region->fresh()->is_published);
    }

    public function test_region_with_places_cannot_be_deleted(): void
    {
        $place = Place::factory()->create();

        $this->admin()->delete("/admin/regions/{$place->region_id}")->assertSessionHasErrors('delete');
        $this->assertTrue(Region::whereKey($place->region_id)->exists());
    }

    public function test_empty_region_can_be_deleted(): void
    {
        $region = Region::factory()->create();

        $this->admin()->delete("/admin/regions/{$region->id}")->assertRedirect(route('admin.regions.index'));
        $this->assertFalse(Region::whereKey($region->id)->exists());
    }

    public function test_category_cannot_be_unpublished_if_it_is_the_last_published_one_of_a_published_place(): void
    {
        $place = Place::factory()->published()->create();
        $category = $place->categories->first() ?? $place->categories()->first();

        $this->admin()->put("/admin/categories/{$category->id}", [
            'name' => $category->name, 'slug' => $category->slug, 'is_published' => '0',
        ])->assertSessionHasErrors('is_published');
    }

    public function test_category_can_be_unpublished_when_the_place_has_another_published_category(): void
    {
        $place = Place::factory()->published()->create();
        $first = $place->categories()->first();
        $place->categories()->attach(Category::factory()->create());

        $this->admin()->put("/admin/categories/{$first->id}", [
            'name' => $first->name, 'slug' => $first->slug, 'is_published' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_published);
    }

    public function test_category_used_by_a_published_place_cannot_be_deleted(): void
    {
        $place = Place::factory()->published()->create();
        $category = $place->categories()->first();

        $this->admin()->delete("/admin/categories/{$category->id}")->assertSessionHasErrors('delete');
    }

    public function test_tag_requires_a_valid_type(): void
    {
        $this->admin()->post('/admin/tags', ['name' => 'Kerajinan', 'tag_type' => 'bogus'])
            ->assertSessionHasErrors('tag_type');

        $this->admin()->post('/admin/tags', ['name' => 'Kerajinan', 'tag_type' => 'theme'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Tag::where('slug', 'kerajinan')->exists());
    }

    public function test_regular_user_cannot_manage_taxonomy(): void
    {
        $this->actingAs($this->makeUser())->get('/admin/regions')->assertForbidden();
        $this->actingAs($this->makeUser())->post('/admin/tags', ['name' => 'X', 'tag_type' => 'theme'])->assertForbidden();
    }
}

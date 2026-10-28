<?php

namespace Tests\Feature;

use App\Enums\RegionType;
use App\Models\Collection;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class RegionAndCollectionPagesTest extends TestCase
{
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_region_index_lists_only_regions_with_published_places(): void
    {
        $filled = Region::factory()->create(['name' => 'Wilayah Isi']);
        $empty = Region::factory()->create(['name' => 'Wilayah Hampa']);
        $unpublished = Region::factory()->unpublished()->create(['name' => 'Wilayah Tersembunyi']);
        Place::factory()->published()->create(['region_id' => $filled->id]);
        Place::factory()->create(['region_id' => $empty->id]);
        Place::factory()->published()->create(['region_id' => $unpublished->id]);

        $this->get('/regions')->assertOk()
            ->assertSee('Wilayah Isi')
            ->assertDontSee('Wilayah Hampa')
            ->assertDontSee('Wilayah Tersembunyi');
    }

    public function test_region_index_separates_administrative_from_editorial_areas(): void
    {
        $admin = Region::factory()->create(['name' => 'Kabupaten Uji', 'region_type' => RegionType::Administrative]);
        $editorial = Region::factory()->create(['name' => 'Area Uji', 'region_type' => RegionType::Editorial]);
        Place::factory()->published()->create(['region_id' => $admin->id]);
        Place::factory()->published()->create(['region_id' => $editorial->id]);

        $this->get('/regions')->assertOk()
            ->assertSeeInOrder(['Kabupaten dan kota', 'Kabupaten Uji', 'Area editorial', 'Area Uji']);
    }

    public function test_region_page_lists_published_places_only_and_404s_when_unpublished(): void
    {
        $region = Region::factory()->create(['slug' => 'ubud', 'name' => 'Ubud']);
        Place::factory()->published()->create(['region_id' => $region->id, 'name' => 'Terbit di Ubud']);
        Place::factory()->create(['region_id' => $region->id, 'name' => 'Draf di Ubud']);
        Region::factory()->unpublished()->create(['slug' => 'rahasia']);

        $this->get('/regions/ubud')->assertOk()->assertSee('Terbit di Ubud')->assertDontSee('Draf di Ubud');
        $this->get('/regions/rahasia')->assertNotFound();
    }

    public function test_editorial_region_page_explains_its_boundaries(): void
    {
        Region::factory()->create(['slug' => 'area-uji', 'region_type' => RegionType::Editorial]);

        $this->get('/regions/area-uji')->assertOk()->assertSee('Area editorial');
    }

    public function test_collection_page_shows_published_places_in_order_with_editor_notes(): void
    {
        $collection = Collection::factory()->create(['slug' => 'pagi-tenang', 'title' => 'Pagi Tenang']);
        $collection->forceFill(['status' => 'published'])->save();

        $first = Place::factory()->published()->create(['name' => 'Tempat Pertama']);
        $second = Place::factory()->published()->create(['name' => 'Tempat Kedua']);
        $hidden = Place::factory()->create(['name' => 'Tempat Draf']);

        $collection->places()->attach($second, ['sort_order' => 2, 'editor_note' => 'Datang sebelum jam delapan.']);
        $collection->places()->attach($first, ['sort_order' => 1]);
        $collection->places()->attach($hidden, ['sort_order' => 3]);

        $this->get('/collections/pagi-tenang')->assertOk()
            ->assertSeeInOrder(['Tempat Pertama', 'Tempat Kedua'])
            ->assertSee('Datang sebelum jam delapan.')
            ->assertDontSee('Tempat Draf');
    }

    public function test_draft_collection_is_404(): void
    {
        Collection::factory()->create(['slug' => 'draf-koleksi']);

        $this->get('/collections/draf-koleksi')->assertNotFound();
    }

    public function test_place_page_links_to_its_published_collections_only(): void
    {
        $place = Place::factory()->published()->create(['slug' => 'tempat-koleksi']);

        $published = Collection::factory()->create(['title' => 'Koleksi Terbit']);
        $published->forceFill(['status' => 'published'])->save();
        $draft = Collection::factory()->create(['title' => 'Koleksi Draf']);

        $published->places()->attach($place);
        $draft->places()->attach($place);

        $this->get('/places/tempat-koleksi')->assertOk()->assertSee('Koleksi Terbit')->assertDontSee('Koleksi Draf');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use App\Support\MapMarkers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class MapComponentTest extends TestCase
{
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_no_map_is_rendered_without_a_browser_key(): void
    {
        config(['archive.maps.browser_key' => null]);
        Place::factory()->published()->create(['slug' => 'tanpa-key']);

        $this->get('/places/tanpa-key')->assertOk()
            ->assertDontSee('archiveMap')
            ->assertSee('Buka di Google Maps');
    }

    public function test_place_page_renders_a_map_when_a_key_exists(): void
    {
        config(['archive.maps.browser_key' => 'KEY_UJI', 'archive.maps.map_id' => 'MAP_UJI']);
        Place::factory()->published()->create(['slug' => 'dengan-key', 'name' => 'Pura Peta']);

        $this->get('/places/dengan-key')->assertOk()
            ->assertSee('archiveMap')
            ->assertSee('Peta lokasi Pura Peta')
            ->assertSee('Tampilkan peta');
    }

    public function test_marker_data_is_encoded_and_cannot_break_out_of_the_attribute(): void
    {
        config(['archive.maps.browser_key' => 'KEY_UJI']);
        Place::factory()->published()->create([
            'slug' => 'xss-peta',
            'name' => '"></section><script>alert(1)</script>',
        ]);

        $this->get('/places/xss-peta')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('"></section><script>', false);
    }

    public function test_hotel_region_and_collection_pages_include_a_map_only_when_there_is_something_to_show(): void
    {
        config(['archive.maps.browser_key' => 'KEY_UJI']);

        Hotel::factory()->published()->create(['slug' => 'hotel-peta']);
        $region = Region::factory()->create(['slug' => 'wilayah-peta']);
        Place::factory()->published()->create(['region_id' => $region->id]);
        Region::factory()->create(['slug' => 'wilayah-kosong']);

        $collection = Collection::factory()->create(['slug' => 'koleksi-peta']);
        $collection->forceFill(['status' => 'published'])->save();
        $collection->places()->attach(Place::factory()->published()->create());

        $this->get('/hotels/hotel-peta')->assertOk()->assertSee('archiveMap');
        $this->get('/regions/wilayah-peta')->assertOk()->assertSee('archiveMap');
        $this->get('/regions/wilayah-kosong')->assertOk()->assertDontSee('archiveMap');
        $this->get('/collections/koleksi-peta')->assertOk()->assertSee('archiveMap');
    }

    public function test_pages_without_maps_do_not_load_the_map_component(): void
    {
        config(['archive.maps.browser_key' => 'KEY_UJI']);

        $this->get('/')->assertOk()->assertDontSee('archiveMap');
        $this->get('/places')->assertOk()->assertDontSee('archiveMap');
    }

    public function test_the_playground_route_does_not_exist_outside_local(): void
    {
        $this->get('/dev/maps')->assertNotFound();
    }

    public function test_marker_builder_numbers_places_and_links_them_to_list_items(): void
    {
        $a = Place::factory()->published()->create(['slug' => 'a', 'lat' => -8.5, 'lng' => 115.2]);
        $b = Place::factory()->published()->create(['slug' => 'b']);

        $plain = MapMarkers::forPlaces([$a, $b]);
        $numbered = MapMarkers::forPlaces([$a, $b], numbered: true);

        $this->assertNull($plain[0]['label']);
        $this->assertSame(['1', '2'], array_column($numbered, 'label'));
        $this->assertSame("map-item-{$a->id}", $numbered[0]['focusId']);
        $this->assertSame('place-'.$a->id, $numbered[0]['id']);
        $this->assertSame(-8.5, $numbered[0]['lat']);
        $this->assertSame(route('places.show', 'a'), $numbered[0]['href']);
    }

    public function test_hotel_marker_is_labelled_h_and_has_no_focus_target(): void
    {
        $hotel = Hotel::factory()->published()->create(['slug' => 'hotel-h']);

        $marker = MapMarkers::forHotel($hotel)[0];

        $this->assertSame('H', $marker['label']);
        $this->assertSame('hotel', $marker['kind']);
        $this->assertArrayNotHasKey('focusId', $marker);
    }

    public function test_enabled_follows_the_browser_key(): void
    {
        config(['archive.maps.browser_key' => null]);
        $this->assertFalse(MapMarkers::enabled());

        config(['archive.maps.browser_key' => 'KEY_UJI']);
        $this->assertTrue(MapMarkers::enabled());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_empty_archive_renders_an_honest_empty_state(): void
    {
        $this->get('/')->assertOk()->assertSee('Arsip sedang disusun');
    }

    public function test_only_published_places_appear(): void
    {
        Place::factory()->published()->create(['name' => 'Tempat Terbit Uji']);
        Place::factory()->create(['name' => 'Tempat Draf Uji']);

        $this->get('/')->assertOk()->assertSee('Tempat Terbit Uji')->assertDontSee('Tempat Draf Uji');
    }

    public function test_featured_place_is_shown_only_when_published(): void
    {
        Place::factory()->published()->featured()->create(['name' => 'Sorotan Terbit']);
        Place::factory()->featured()->create(['name' => 'Sorotan Draf']);

        $this->get('/')->assertOk()->assertSee('Sorotan Terbit')->assertDontSee('Sorotan Draf');
    }

    public function test_featured_place_is_not_repeated_in_the_latest_list(): void
    {
        Place::factory()->published()->featured()->create(['name' => 'Sorotan Unik']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Sorotan Unik</a>'));
    }

    public function test_regions_without_published_places_are_hidden(): void
    {
        $visible = Region::factory()->create(['name' => 'Wilayah Berisi']);
        $hidden = Region::factory()->create(['name' => 'Wilayah Kosong']);
        Place::factory()->published()->create(['region_id' => $visible->id]);
        Place::factory()->create(['region_id' => $hidden->id]);

        $this->get('/')->assertOk()->assertSee('Wilayah Berisi')->assertDontSee('Wilayah Kosong');
    }

    public function test_hotels_section_lists_published_hotels_and_marks_missing_prices(): void
    {
        Hotel::factory()->published()->create(['name' => 'Penginapan Terbit']);
        Hotel::factory()->create(['name' => 'Penginapan Draf']);

        $this->get('/')->assertOk()
            ->assertSee('Penginapan Terbit')
            ->assertSee('Harga tidak tersedia')
            ->assertDontSee('Penginapan Draf');
    }

    public function test_planner_links_are_hidden_until_the_feature_is_enabled(): void
    {
        $this->app->setLocale('id');
        $this->get('/')->assertOk()->assertDontSee('Rencanakan perjalanan');

        config(['archive.features.planner' => true]);

        $this->get('/')->assertOk()->assertSee('Rencanakan perjalanan');
    }
}

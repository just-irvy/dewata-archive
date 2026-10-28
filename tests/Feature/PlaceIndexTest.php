<?php

namespace Tests\Feature;

use App\Enums\TagType;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class PlaceIndexTest extends TestCase
{
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_lists_only_published_places(): void
    {
        Place::factory()->published()->create(['name' => 'Pura Terbit']);
        Place::factory()->create(['name' => 'Pura Draf']);
        $archived = Place::factory()->published()->create(['name' => 'Pura Arsip']);
        $archived->forceFill(['status' => 'archived'])->save();

        $this->get('/places')->assertOk()
            ->assertSee('Pura Terbit')
            ->assertDontSee('Pura Draf')
            ->assertDontSee('Pura Arsip');
    }

    public function test_empty_states_differ_with_and_without_filters(): void
    {
        $this->get('/places')->assertOk()->assertSee('Belum ada tempat terbit');

        Place::factory()->published()->create();

        $this->get('/places?q=tidak-akan-ketemu')->assertOk()->assertSee('Tidak ada tempat yang cocok');
    }

    public function test_filters_by_region(): void
    {
        $gianyar = Region::factory()->create(['slug' => 'gianyar', 'name' => 'Gianyar']);
        Place::factory()->published()->create(['name' => 'Di Gianyar', 'region_id' => $gianyar->id]);
        Place::factory()->published()->create(['name' => 'Di Tempat Lain']);

        $this->get('/places?region=gianyar')->assertOk()
            ->assertSee('Di Gianyar')
            ->assertDontSee('Di Tempat Lain')
            ->assertSee('Wilayah: Gianyar');
    }

    public function test_filters_by_category_and_by_any_selected_tag(): void
    {
        $category = Category::factory()->create(['slug' => 'pura', 'name' => 'Pura']);
        $craft = Tag::factory()->create(['slug' => 'kerajinan', 'name' => 'Kerajinan', 'tag_type' => TagType::Theme]);
        $food = Tag::factory()->create(['slug' => 'kuliner', 'name' => 'Kuliner', 'tag_type' => TagType::Theme]);

        $a = Place::factory()->published()->create(['name' => 'Tempat A']);
        $a->categories()->attach($category);
        $a->tags()->attach($craft);

        $b = Place::factory()->published()->create(['name' => 'Tempat B']);
        $b->tags()->attach($food);

        Place::factory()->published()->create(['name' => 'Tempat C']);

        $this->get('/places?category=pura')->assertSee('Tempat A')->assertDontSee('Tempat B');

        $this->get('/places?tags[]=kerajinan&tags[]=kuliner')
            ->assertSee('Tempat A')->assertSee('Tempat B')->assertDontSee('Tempat C');
    }

    public function test_search_matches_name_or_summary(): void
    {
        Place::factory()->published()->create(['name' => 'Air Terjun Sekumpul', 'summary' => 'Dua puluh menit berjalan kaki.']);
        Place::factory()->published()->create(['name' => 'Pasar Seni', 'summary' => 'Kerajinan anyaman bambu.']);

        $this->get('/places?q=sekumpul')->assertSee('Air Terjun Sekumpul')->assertDontSee('Pasar Seni');
        $this->get('/places?q=anyaman')->assertSee('Pasar Seni')->assertDontSee('Air Terjun Sekumpul');
    }

    public function test_accessibility_filter_counts_only_verified_data(): void
    {
        Place::factory()->published()->create([
            'name' => 'Akses Terverifikasi',
            'accessibility' => ['step_free' => 'yes'],
            'accessibility_status' => VerificationStatus::Verified,
        ]);
        Place::factory()->published()->create([
            'name' => 'Akses Belum Diverifikasi',
            'accessibility' => ['step_free' => 'yes'],
            'accessibility_status' => VerificationStatus::Unknown,
        ]);

        $this->get('/places?access[]=step_free')->assertOk()
            ->assertSee('Akses Terverifikasi')
            ->assertDontSee('Akses Belum Diverifikasi');
    }

    public function test_accessibility_options_appear_only_when_verified_data_exists(): void
    {
        Place::factory()->published()->create();
        $this->get('/places')->assertOk()->assertDontSee('Akses tanpa tangga');

        Place::factory()->published()->create([
            'accessibility' => ['step_free' => 'yes'],
            'accessibility_status' => VerificationStatus::Verified,
        ]);
        $this->get('/places')->assertOk()->assertSee('Akses tanpa tangga');
    }

    public function test_filter_options_list_only_values_that_have_published_places(): void
    {
        $withPlaces = Category::factory()->create(['name' => 'Kategori Berisi']);
        $withoutPlaces = Category::factory()->create(['name' => 'Kategori Kosong']);
        Place::factory()->published()->create()->categories()->attach($withPlaces);

        $this->get('/places')->assertOk()->assertSee('Kategori Berisi')->assertDontSee('Kategori Kosong');
    }

    public function test_invalid_filter_input_is_rejected(): void
    {
        $this->get('/places?access[]=bogus')->assertSessionHasErrors('access.0');
        $this->get('/places?sort=acak')->assertSessionHasErrors('sort');
    }

    public function test_results_are_paginated(): void
    {
        config(['archive.per_page' => 2]);
        Place::factory()->published()->count(3)->create();

        $this->get('/places')->assertOk()->assertSee('Halaman 1 dari 2');
        $this->get('/places?page=2')->assertOk()->assertSee('Halaman 2 dari 2');
    }

    public function test_sort_by_name(): void
    {
        Place::factory()->published()->create(['name' => 'Zeta Uji']);
        Place::factory()->published()->create(['name' => 'Alfa Uji']);

        $this->get('/places?sort=nama')->assertSeeInOrder(['Alfa Uji', 'Zeta Uji']);
    }
}

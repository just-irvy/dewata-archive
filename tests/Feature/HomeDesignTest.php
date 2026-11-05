<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\SiteSetting;
use App\Support\ArchiveCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\BuildsPlannerData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

/** Beranda versi desain Stitch: angka dan kode berasal dari data, masthead menumpang foto bila ada sampul. */
class HomeDesignTest extends TestCase
{
    use BuildsArchiveData;
    use BuildsPlannerData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_collection_bar_counts_only_published_records(): void
    {
        $this->seedCatalog();
        $this->spot($this->gianyar, 'Draf', -8.5, 115.2, [], [], PublishStatus::Draft);
        Hotel::factory()->published()->create(['region_id' => $this->gianyar->id]);

        $this->get('/')->assertOk()
            ->assertSee('10 tempat · 1 penginapan · 2 wilayah')
            ->assertSee('Lihat seluruh 10 entri');
    }

    public function test_cards_show_archive_code_coordinates_and_verification_stamp(): void
    {
        $this->seedCatalog();
        $place = Place::query()->latest('id')->first();

        $this->get('/')->assertOk()
            ->assertSee(ArchiveCode::for($place))
            ->assertSee(ArchiveCode::dms($place->lat, $place->lng))
            ->assertSee('[Terverifikasi]');
    }

    public function test_masthead_sits_on_the_cover_photo_only_when_a_cover_is_set(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Gulir ke arsip');

        SiteSetting::put(SiteSetting::HOME_COVER, ['path' => 'site/x.jpg', 'alt_text' => 'Sawah', 'credit' => 'Penguji']);
        $html = $this->get('/')->assertOk()->assertSee('Foto: Penguji')->getContent();

        // Hanya masthead yang dihitung. Komponen lain (misalnya widget chat) boleh punya <header> sendiri.
        $this->assertSame(1, substr_count($html, 'data-masthead'));
        $this->assertStringContainsString('Gulir ke arsip', $html);
    }

    public function test_design_claims_that_contradict_the_product_are_not_shown(): void
    {
        $this->seedCatalog();
        $this->get('/')->assertOk()
            ->assertDontSee('Bebas algoritma')
            ->assertDontSee('Tanpa Beban Algoritma')
            ->assertDontSee('DOKUMEN RESMI');
    }

    public function test_dms_formatting(): void
    {
        $this->assertSame('8°37′16″S 115°05′14″E', ArchiveCode::dms(-8.62107, 115.08716));
        $this->assertNull(ArchiveCode::dms(null, 115.0));
    }
}

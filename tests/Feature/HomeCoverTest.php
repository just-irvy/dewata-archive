<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsArchiveData;
use Tests\Support\BuildsPlannerData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

/** Foto sampul Pulau Bali di beranda, dikelola admin dengan aturan hak pakai yang sama. */
class HomeCoverTest extends TestCase
{
    use BuildsArchiveData;
    use BuildsPlannerData;
    use RefreshDatabase;
    use WithoutViteAssets;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('archive.media.disk'));
    }

    private function upload(array $overrides = [])
    {
        return $this->post(route('admin.home.update'), array_replace([
            'file' => UploadedFile::fake()->image('bali.jpg', 1600, 2000),
            'alt_text' => 'Sawah berundak Jatiluwih saat pagi',
            'caption' => 'Jatiluwih, Tabanan',
            'credit' => 'Tim The Dewata Archive',
            'license_note' => 'Foto milik sendiri',
        ], $overrides));
    }

    public function test_without_a_cover_the_home_page_shows_the_bali_plate(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Peta Pulau Bali')
            ->assertSee('Garis pantai: Natural Earth')
            ->assertDontSee('Pasang foto sampul');
    }

    public function test_admin_uploads_a_cover_and_it_replaces_the_plate(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));
        $this->upload()->assertRedirect(route('admin.home.edit'))->assertSessionHasNoErrors();

        $cover = SiteSetting::homeCover();
        $this->assertNotNull($cover);
        $this->assertStringStartsWith('site/', $cover['path']);
        Storage::disk(config('archive.media.disk'))->assertExists($cover['path']);
        $this->assertTrue(AuditLog::query()->where('action', 'site.home_cover.updated')->exists());

        $this->get('/')->assertOk()
            ->assertSee($cover['url'], false)
            ->assertSee('alt="Sawah berundak Jatiluwih saat pagi"', false)
            ->assertSee('Foto: Tim The Dewata Archive, Foto milik sendiri')
            ->assertDontSee('Peta Pulau Bali');
    }

    public function test_alt_text_and_usage_rights_are_required(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));

        $this->upload(['alt_text' => ''])->assertSessionHasErrors('alt_text');
        $this->upload(['credit' => '', 'license_note' => ''])->assertSessionHasErrors(['credit', 'license_note']);
        $this->upload(['credit' => '', 'license_note' => 'CC BY 4.0'])->assertSessionHasNoErrors();
    }

    public function test_small_or_non_image_files_are_rejected(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));

        $this->upload(['file' => UploadedFile::fake()->image('kecil.jpg', 600, 400)])->assertSessionHasErrors('file');
        $this->upload(['file' => UploadedFile::fake()->create('skrip.php', 10, 'text/x-php')])->assertSessionHasErrors('file');
        $this->assertNull(SiteSetting::homeCover());
    }

    public function test_caption_can_change_without_a_new_file_and_replacing_deletes_the_old_file(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));
        $this->upload();
        $first = SiteSetting::homeCover()['path'];

        $this->upload(['file' => null, 'caption' => 'Tegallalang'])->assertSessionHasNoErrors();
        $this->assertSame($first, SiteSetting::homeCover()['path']);
        $this->assertSame('Tegallalang', SiteSetting::homeCover()['caption']);

        $this->upload();
        Storage::disk(config('archive.media.disk'))->assertMissing($first);
    }

    public function test_deleting_the_cover_brings_back_the_plate(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));
        $this->upload();
        $path = SiteSetting::homeCover()['path'];

        $this->delete(route('admin.home.destroy'))->assertRedirect(route('admin.home.edit'));

        $this->assertNull(SiteSetting::homeCover());
        Storage::disk(config('archive.media.disk'))->assertMissing($path);
        $this->get('/')->assertSee('Peta Pulau Bali');
    }

    public function test_regular_users_cannot_manage_the_cover(): void
    {
        $this->actingAs($this->makeUser());

        $this->get(route('admin.home.edit'))->assertForbidden();
        $this->upload()->assertForbidden();
        $this->assertNull(SiteSetting::homeCover());
    }

    public function test_admins_see_a_shortcut_to_set_the_cover(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))->get('/')->assertSee('Pasang foto sampul Pulau Bali');
    }

    public function test_plate_dots_follow_published_coordinates_only(): void
    {
        $region = $this->region('gianyar', 'Gianyar');
        $this->spot($region, 'Pura Saraswati', -8.5069, 115.2625);
        $this->spot($region, 'Pura Draf', -8.4, 115.2, [], [], PublishStatus::Draft);

        $html = $this->get('/')->getContent();
        $this->assertSame(1, substr_count($html, 'fill="#9C4935" stroke="#F6F0E4"'));
        $this->assertStringContainsString('1 tempat terbit di arsip', $html);
    }
}

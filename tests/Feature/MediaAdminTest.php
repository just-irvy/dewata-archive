<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class MediaAdminTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function upload(Place $place, UploadedFile $file, array $extra = [])
    {
        return $this->actingAs($this->makeUser(UserRole::Admin))->post('/admin/media', [
            'mediable_type' => 'place',
            'mediable_id' => $place->id,
            'file' => $file,
        ] + $extra);
    }

    public function test_valid_image_is_stored_with_a_random_name_and_dimensions(): void
    {
        $place = Place::factory()->create();

        $this->upload($place, UploadedFile::fake()->image('foto-asli-saya.png', 1200, 800), [
            'alt_text' => 'Sawah berundak saat pagi', 'credit' => 'Fotografer Uji',
        ])->assertSessionHasNoErrors();

        $media = Media::query()->firstOrFail();

        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[A-Za-z0-9]{40}\.png$#', $media->path);
        $this->assertStringNotContainsString('foto-asli-saya', $media->path);
        $this->assertSame(1200, $media->width);
        $this->assertSame($place->id, $media->mediable_id);
        Storage::disk('public')->assertExists($media->path);
        $this->assertTrue(AuditLog::where('action', 'media.created')->exists());
    }

    public function test_svg_is_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('gambar.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

        $this->upload(Place::factory()->create(), $svg)->assertSessionHasErrors('file');
        $this->assertSame(0, Media::count());
    }

    public function test_non_image_is_rejected(): void
    {
        $pdf = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $this->upload(Place::factory()->create(), $pdf)->assertSessionHasErrors('file');
        $this->assertSame(0, Media::count());
    }

    public function test_regular_user_cannot_upload(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->makeUser())->post('/admin/media', [
            'mediable_type' => 'place', 'mediable_id' => $place->id,
            'file' => UploadedFile::fake()->image('a.png'),
        ])->assertForbidden();
    }

    public function test_metadata_can_be_updated_and_makes_the_photo_publishable(): void
    {
        $media = Media::factory()->withoutAlt()->for(Place::factory()->create(), 'mediable')->create(['credit' => null, 'license_note' => null]);
        $this->assertFalse($media->isPublishable());

        $this->actingAs($this->makeUser(UserRole::Admin))->put("/admin/media/{$media->id}", [
            'alt_text' => 'Pura di tepi sawah', 'credit' => 'Fotografer Uji',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($media->fresh()->isPublishable());
    }

    public function test_deleting_removes_the_file_and_the_record(): void
    {
        $place = Place::factory()->create();
        $this->upload($place, UploadedFile::fake()->image('a.jpg'));
        $media = Media::query()->firstOrFail();
        $path = $media->path;

        $this->actingAs($this->makeUser(UserRole::Admin))->delete("/admin/media/{$media->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, Media::count());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsArchiveData;
use Tests\TestCase;

class ImportPhotosCommandTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->root = sys_get_temp_dir().'/foto-'.uniqid();
        mkdir($this->root.'/places', 0777, true);
        mkdir($this->root.'/hotels', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->root.'/*') ?: [] as $file) {
            is_dir($file) ? @rmdir($file) : @unlink($file);
        }
        @rmdir($this->root);
        parent::tearDown();
    }

    private function image(string $relative, int $w = 800, int $h = 600): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, 10, 10, imagecolorallocate($im, random_int(0, 255), 80, 80));
        $path = $this->root.'/'.$relative;
        str_ends_with($relative, '.png') ? imagepng($im, $path) : imagejpeg($im, $path, 85);
    }

    private function runImport(array $options = [])
    {
        return $this->artisan('archive:import-photos', ['--path' => $this->root] + $options);
    }

    public function test_it_refuses_to_run_without_a_statement_of_rights(): void
    {
        Place::factory()->create(['name' => 'Museum Yadnya', 'slug' => 'museum-yadnya']);
        $this->image('places/museum-yadnya.jpg');

        $this->runImport()->expectsOutputToContain('Nyatakan hak pakai')->assertExitCode(1);

        $this->assertSame(0, Media::count());
    }

    public function test_it_matches_by_slug_by_name_and_by_numeric_suffix_and_stores_the_rights(): void
    {
        $museum = Place::factory()->create(['name' => 'Museum Yadnya', 'slug' => 'museum-yadnya']);
        $pura = Place::factory()->create(['name' => 'Pura Lain', 'slug' => 'pura-lain-ubud']);
        $this->image('places/museum-yadnya.jpg');
        $this->image('places/museum-yadnya-2.jpg');
        $this->image('places/Pura Lain.jpg');

        $this->runImport(['--credit' => 'Tim Uji', '--license' => 'Foto milik sendiri'])
            ->expectsOutputToContain('Foto dipasang: 3')
            ->assertExitCode(0);

        $photos = $museum->media()->get();
        $this->assertSame([1, 2], $photos->pluck('sort_order')->all());
        $this->assertSame(1, $pura->media()->count());

        $first = $photos->first();
        $this->assertSame('Foto Museum Yadnya', $first->alt_text);
        $this->assertSame('Tim Uji', $first->credit);
        $this->assertSame('Foto milik sendiri', $first->license_note);
        $this->assertSame([800, 600], [$first->width, $first->height]);
        $this->assertNotNull($first->source_hash);
        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[A-Za-z0-9]{40}\.jpg$#', $first->path);
        $this->assertStringNotContainsString('museum', $first->path);
        Storage::disk('public')->assertExists($first->path);
        $this->assertTrue($first->isPublishable());
        $this->assertSame(3, AuditLog::where('action', 'media.imported')->count());
    }

    public function test_the_sidecar_file_sets_alt_text_and_rights_per_photo(): void
    {
        $place = Place::factory()->create(['name' => 'Pura Uji', 'slug' => 'pura-uji']);
        $this->image('places/pura-uji.jpg');
        file_put_contents($this->root.'/keterangan.csv', "file,alt_text,caption,credit,license_note\npura-uji.jpg,Gerbang candi bentar dengan tangga batu,Gerbang utama,Budi Santoso,CC BY 4.0\n");

        $this->runImport()->expectsOutputToContain('Foto dipasang: 1')->assertExitCode(0);

        $media = $place->media()->firstOrFail();
        $this->assertSame('Gerbang candi bentar dengan tangga batu', $media->alt_text);
        $this->assertSame('Gerbang utama', $media->caption);
        $this->assertSame('Budi Santoso', $media->credit);
        $this->assertSame('CC BY 4.0', $media->license_note);
    }

    public function test_running_it_twice_does_not_duplicate_photos(): void
    {
        $place = Place::factory()->create(['name' => 'Museum Yadnya', 'slug' => 'museum-yadnya']);
        $this->image('places/museum-yadnya.jpg');

        $this->runImport(['--credit' => 'Tim Uji'])->assertExitCode(0);
        $this->runImport(['--credit' => 'Tim Uji'])->expectsOutputToContain('Dilewati karena sudah ada: 1')->assertExitCode(0);

        $this->assertSame(1, $place->media()->count());
    }

    public function test_large_photos_are_scaled_down(): void
    {
        $place = Place::factory()->create(['name' => 'Pura Besar', 'slug' => 'pura-besar']);
        $this->image('places/pura-besar.jpg', 3000, 1500);

        $this->runImport(['--credit' => 'Tim Uji'])->assertExitCode(0);

        $media = $place->media()->firstOrFail();
        $this->assertSame([2000, 1000], [$media->width, $media->height]);
        $this->assertSame(2000, getimagesize(Storage::disk('public')->path($media->path))[0]);
    }

    public function test_fake_images_and_unsupported_files_are_rejected_but_the_rest_still_installs(): void
    {
        $place = Place::factory()->create(['name' => 'Pura Uji', 'slug' => 'pura-uji']);
        $this->image('places/pura-uji.jpg');
        file_put_contents($this->root.'/places/pura-uji-2.jpg', 'ini bukan gambar');
        file_put_contents($this->root.'/places/pura-uji-3.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->runImport(['--credit' => 'Tim Uji'])
            ->expectsOutputToContain('Foto dipasang: 1')
            ->expectsOutputToContain('Tidak valid: 2')
            ->assertExitCode(0);

        $this->assertSame(1, $place->media()->count());
    }

    public function test_unmatched_and_ambiguous_names_are_reported_not_guessed(): void
    {
        Place::factory()->create(['name' => 'Sama', 'slug' => 'sama-satu']);
        Place::factory()->create(['name' => 'Sama', 'slug' => 'sama-dua']);
        $this->image('places/sama.jpg');
        $this->image('places/tidak-ada.jpg');

        $this->runImport(['--credit' => 'Tim Uji'])
            ->expectsOutputToContain('Nama ambigu')
            ->expectsOutputToContain('Tidak cocok dengan tempat atau hotel mana pun')
            ->expectsOutputToContain('places/tidak-ada.jpg')
            ->assertExitCode(0);

        $this->assertSame(0, Media::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        Place::factory()->create(['name' => 'Museum Yadnya', 'slug' => 'museum-yadnya']);
        $this->image('places/museum-yadnya.jpg');

        $this->runImport(['--dry-run' => true])->expectsOutputToContain('SIMULASI')->assertExitCode(0);

        $this->assertSame(0, Media::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, AuditLog::where('action', 'media.imported')->count());
    }

    public function test_hotels_use_their_own_folder(): void
    {
        $hotel = Hotel::factory()->create(['name' => 'Villa Uji', 'slug' => 'villa-uji']);
        $this->image('hotels/villa-uji.jpg');

        $this->runImport(['--credit' => 'Tim Uji'])->assertExitCode(0);

        $this->assertSame(1, $hotel->media()->count());
    }

    public function test_installed_photos_make_a_place_publishable_without_a_photo_warning(): void
    {
        $place = Place::factory()->ready()->create(['name' => 'Pura Siap', 'slug' => 'pura-siap']);
        $this->image('places/pura-siap.jpg');
        $this->runImport(['--credit' => 'Tim Uji'])->assertExitCode(0);

        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/places/{$place->id}/status", ['to' => 'published'])
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $place->fresh()->status->value);
    }

    public function test_the_photo_list_command_creates_folders_and_lists_expected_file_names(): void
    {
        $without = Place::factory()->create(['name' => 'Tanpa Foto', 'slug' => 'tanpa-foto']);
        $without->forceFill(['external_id' => 'combined150:B64'])->save();
        $with = Place::factory()->create(['name' => 'Berfoto', 'slug' => 'berfoto']);
        Media::factory()->for($with, 'mediable')->create();
        $root = sys_get_temp_dir().'/daftar-'.uniqid();

        $this->artisan('archive:photo-list', ['--path' => $root])->assertExitCode(0);

        $this->assertDirectoryExists($root.'/places');
        $this->assertDirectoryExists($root.'/hotels');
        $this->assertFileExists($root.'/keterangan.csv');

        $list = file_get_contents($root.'/daftar-nama-foto.csv');
        $this->assertStringContainsString('places/tanpa-foto.jpg', $list);
        $this->assertStringContainsString('id_dataset', $list);
        $this->assertStringContainsString('combined150:B64', $list);
        $this->assertStringNotContainsString('berfoto', $list);

        $this->artisan('archive:photo-list', ['--path' => $root, '--all' => true])->assertExitCode(0);
        $this->assertStringContainsString('places/berfoto.jpg', file_get_contents($root.'/daftar-nama-foto.csv'));

        array_map('unlink', glob($root.'/*.csv'));
        rmdir($root.'/places');
        rmdir($root.'/hotels');
        rmdir($root);
    }
}

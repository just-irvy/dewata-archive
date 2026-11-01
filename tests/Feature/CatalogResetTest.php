<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\Place;
use App\Models\TripPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsArchiveData;
use Tests\Support\BuildsPlannerData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class CatalogResetTest extends TestCase
{
    use BuildsArchiveData;
    use BuildsPlannerData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private TripPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('archive.media.disk'));
        $this->seedCatalog();

        $place = Place::query()->first();
        $path = UploadedFile::fake()->image('foto.jpg')->store('media', config('archive.media.disk'));
        $place->media()->forceCreate(['path' => $path, 'alt_text' => 'Foto', 'credit' => 'Uji']);

        $hotel = Hotel::factory()->published()->create(['region_id' => $this->gianyar->id]);
        Place::query()->skip(1)->first()->delete(); // dihapus sementara: tetap harus hilang

        $user = $this->makeUser();
        $user->savedPlaces()->create(['place_id' => $place->id]);
        $this->plan = $user->tripPlans()->create(['title' => 'Rencana', 'creation_mode' => 'manual', 'days_count' => 1]);
        $day = $this->plan->days()->create(['day_number' => 1, 'sort_order' => 1, 'hotel_id' => $hotel->id]);
        $day->items()->create(['place_id' => $place->id, 'item_source' => 'manual', 'sort_order' => 1]);
    }

    private function confirm(array $data = [])
    {
        return $this->delete(route('admin.catalog-reset.destroy'), $data + ['confirmation' => 'HAPUS SEMUA', 'password' => 'password']);
    }

    public function test_super_admin_wipes_every_place_and_hotel_permanently(): void
    {
        $path = Media::query()->value('path');
        $this->actingAs($this->makeUser(UserRole::SuperAdmin));

        $this->get(route('admin.catalog-reset.show'))->assertOk()->assertSee('Hapus permanen')->assertSee('10');
        $this->confirm()->assertRedirect(route('admin.index'))->assertSessionHas('status');

        $this->assertSame(0, Place::withTrashed()->count());
        $this->assertSame(0, Hotel::withTrashed()->count());
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('saved_places', 0);
        $this->assertDatabaseCount('place_tag', 0);
        $this->assertDatabaseCount('trip_plan_items', 0);
        Storage::disk(config('archive.media.disk'))->assertMissing($path);

        // Rencana pengguna tetap ada, tetapi kosong, dan titik awal penginapannya dilepas.
        $this->assertDatabaseHas('trip_plans', ['id' => $this->plan->id]);
        $this->assertNull($this->plan->days()->first()->hotel_id);

        // Wilayah dan tag tetap.
        $this->assertDatabaseHas('regions', ['slug' => 'gianyar']);
        $this->assertDatabaseHas('tags', ['slug' => 'alam']);

        $log = AuditLog::query()->where('action', 'catalog.reset')->sole();
        $this->assertSame(10, $log->after_json['places']);
    }

    public function test_wrong_phrase_or_password_deletes_nothing(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin));

        $this->confirm(['confirmation' => 'hapus semua'])->assertSessionHasErrors('confirmation');
        $this->confirm(['password' => 'salah'])->assertSessionHasErrors('password');
        $this->assertSame(10, Place::withTrashed()->count());
    }

    public function test_regular_admins_and_users_cannot_reset(): void
    {
        foreach ([$this->makeUser(UserRole::Admin), $this->makeUser()] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.catalog-reset.show'))->assertForbidden();
            $this->confirm()->assertForbidden();
        }

        $this->assertSame(10, Place::withTrashed()->count());
    }

    public function test_command_dry_run_changes_nothing_and_requires_a_super_admin_actor(): void
    {
        $this->artisan('archive:reset-catalog --dry-run')->assertSuccessful();
        $this->assertSame(10, Place::withTrashed()->count());

        $admin = $this->makeUser(UserRole::Admin);
        $this->artisan('archive:reset-catalog --force --actor='.$admin->email)->assertFailed();
        $this->assertSame(10, Place::withTrashed()->count());
    }

    public function test_command_with_confirmation(): void
    {
        $super = $this->makeUser(UserRole::SuperAdmin);

        $this->artisan('archive:reset-catalog --actor='.$super->email)
            ->expectsQuestion('Ketik HAPUS SEMUA untuk melanjutkan. Tindakan ini tidak bisa dibatalkan', 'tidak')
            ->assertFailed();
        $this->assertSame(10, Place::withTrashed()->count());

        $this->artisan('archive:reset-catalog --force --actor='.$super->email)->assertSuccessful();
        $this->assertSame(0, Place::withTrashed()->count());
        $this->assertSame(0, Hotel::withTrashed()->count());
    }
}

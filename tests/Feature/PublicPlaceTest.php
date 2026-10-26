<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class PublicPlaceTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_published_place_is_visible_to_guests(): void
    {
        $place = Place::factory()->published()->create(['name' => 'Pura Tirta Empul', 'slug' => 'pura-tirta-empul']);

        $this->get('/places/pura-tirta-empul')->assertOk()->assertSee('Pura Tirta Empul');
    }

    public function test_draft_and_archived_places_are_404_for_everyone_including_admin(): void
    {
        $draft = Place::factory()->create(['slug' => 'draf']);
        $archived = Place::factory()->published()->create(['slug' => 'arsip']);
        $archived->forceFill(['status' => 'archived'])->save();

        $this->get('/places/draf')->assertNotFound();
        $this->get('/places/arsip')->assertNotFound();
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->get('/places/draf')->assertNotFound();
    }

    public function test_preview_is_for_admins_only_and_marked_as_preview(): void
    {
        $place = Place::factory()->create(['name' => 'Tempat Draf']);

        $this->get("/admin/places/{$place->id}/preview/page")->assertRedirect(route('login'));
        $this->actingAs($this->makeUser())->get("/admin/places/{$place->id}/preview/page")->assertForbidden();

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->get("/admin/places/{$place->id}/preview/page")
            ->assertOk()
            ->assertSee('Tempat Draf')
            ->assertSee('Pratinjau');

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->get("/admin/places/{$place->id}/preview")
            ->assertOk();
    }

    public function test_unverified_hours_are_hidden_and_replaced_by_a_prompt_to_check(): void
    {
        $place = Place::factory()->published()->create([
            'slug' => 'jam',
            'opening_hours' => ['mon' => ['open' => '08:15', 'close' => '17:45']],
            'opening_hours_status' => VerificationStatus::Unknown,
        ]);

        $this->get('/places/jam')
            ->assertOk()
            ->assertSee('Cek jam buka sebelum berkunjung.')
            ->assertDontSee('08:15');
    }

    public function test_verified_hours_are_shown(): void
    {
        Place::factory()->published()->create([
            'slug' => 'jam-ok',
            'opening_hours' => ['mon' => ['open' => '08:15', 'close' => '17:45']],
            'opening_hours_status' => VerificationStatus::Verified,
        ]);

        $this->get('/places/jam-ok')->assertOk()->assertSee('08:15');
    }

    public function test_photos_without_alt_text_or_rights_are_not_shown_publicly(): void
    {
        Storage::fake('public');
        $place = Place::factory()->published()->create(['slug' => 'foto']);
        Media::factory()->withoutAlt()->for($place, 'mediable')->create(['path' => 'media/uji/belum-lengkap.jpg']);
        Media::factory()->for($place, 'mediable')->create(['path' => 'media/uji/lengkap.jpg']);

        $this->get('/places/foto')
            ->assertOk()
            ->assertSee('lengkap.jpg')
            ->assertDontSee('belum-lengkap.jpg');
    }

    public function test_body_is_escaped_and_never_rendered_as_html(): void
    {
        Place::factory()->published()->create([
            'slug' => 'xss',
            'body' => "Paragraf satu.\n\n<script>alert(1)</script>",
        ]);

        $this->get('/places/xss')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}

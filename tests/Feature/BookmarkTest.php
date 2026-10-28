<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Models\Place;
use App\Models\SavedPlace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_guest_is_sent_to_login_and_returned_to_the_place_afterwards(): void
    {
        $place = Place::factory()->published()->create(['slug' => 'pura-uji']);

        $this->get('/places/pura-uji/save')
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('places.show', 'pura-uji'));

        $this->assertSame(0, SavedPlace::count());
    }

    public function test_guest_cannot_write_bookmarks(): void
    {
        Place::factory()->published()->create(['slug' => 'pura-uji']);

        $this->put('/places/pura-uji/bookmark')->assertRedirect(route('login'));
        $this->assertSame(0, SavedPlace::count());
    }

    public function test_saving_is_idempotent_and_removal_works(): void
    {
        $user = $this->makeUser();
        $place = Place::factory()->published()->create(['slug' => 'pura-uji']);

        $this->actingAs($user)->put('/places/pura-uji/bookmark')->assertRedirect();
        $this->actingAs($user)->put('/places/pura-uji/bookmark')->assertRedirect();
        $this->assertSame(1, $user->savedPlaces()->count());

        $this->actingAs($user)->delete('/places/pura-uji/bookmark')->assertRedirect();
        $this->assertSame(0, $user->savedPlaces()->count());
    }

    public function test_draft_places_cannot_be_saved(): void
    {
        Place::factory()->create(['slug' => 'draf']);

        $this->actingAs($this->makeUser())->put('/places/draf/bookmark')->assertNotFound();
        $this->assertSame(0, SavedPlace::count());
    }

    public function test_saved_list_is_private_and_shows_published_places_only(): void
    {
        $mine = $this->makeUser();
        $other = $this->makeUser();

        $kept = Place::factory()->published()->create(['name' => 'Tersimpan Saya']);
        $archived = Place::factory()->published()->create(['name' => 'Tersimpan Lalu Diarsipkan']);
        $theirs = Place::factory()->published()->create(['name' => 'Milik Orang Lain']);

        $mine->savedPlaces()->create(['place_id' => $kept->id]);
        $mine->savedPlaces()->create(['place_id' => $archived->id]);
        $other->savedPlaces()->create(['place_id' => $theirs->id]);
        $archived->forceFill(['status' => PublishStatus::Archived])->save();

        $this->actingAs($mine)->get('/account/saved-places')->assertOk()
            ->assertSee('Tersimpan Saya')
            ->assertDontSee('Tersimpan Lalu Diarsipkan')
            ->assertDontSee('Milik Orang Lain');
    }

    public function test_saved_list_requires_login(): void
    {
        $this->get('/account/saved-places')->assertRedirect(route('login'));
    }

    public function test_place_page_shows_saved_state_for_the_signed_in_user(): void
    {
        $user = $this->makeUser();
        $place = Place::factory()->published()->create(['slug' => 'pura-uji']);

        $this->actingAs($user)->get('/places/pura-uji')->assertOk()->assertSee('Simpan:')->assertDontSee('Tersimpan');

        $user->savedPlaces()->create(['place_id' => $place->id]);

        $this->actingAs($user)->get('/places/pura-uji')->assertOk()->assertSee('Tersimpan');
    }

    public function test_a_saved_place_can_be_removed_even_after_it_was_archived(): void
    {
        $user = $this->makeUser();
        $place = Place::factory()->published()->create(['slug' => 'arsip-uji']);
        $user->savedPlaces()->create(['place_id' => $place->id]);
        $place->forceFill(['status' => PublishStatus::Archived])->save();

        $this->actingAs($user)->delete('/places/arsip-uji/bookmark')->assertRedirect();

        $this->assertSame(0, $user->savedPlaces()->count());
    }

    public function test_users_cannot_remove_other_users_bookmarks(): void
    {
        $owner = $this->makeUser();
        $place = Place::factory()->published()->create(['slug' => 'pura-uji']);
        $owner->savedPlaces()->create(['place_id' => $place->id]);

        $this->actingAs($this->makeUser())->delete('/places/pura-uji/bookmark');

        $this->assertSame(1, $owner->savedPlaces()->count());
    }
}

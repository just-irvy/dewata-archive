<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Place;
use App\Models\TripPlanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class PlaceAdminTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_only_admin_can_open_the_create_form(): void
    {
        $this->get('/admin/places/create')->assertRedirect(route('login'));
        $this->actingAs($this->makeUser())->get('/admin/places/create')->assertForbidden();
        $this->actingAs($this->makeUser(UserRole::Admin))->get('/admin/places/create')->assertOk();
    }

    public function test_store_creates_a_draft_and_ignores_status_and_owner_fields(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post('/admin/places', $this->placePayload([
            'status' => 'published',
            'created_by' => 999,
        ]))->assertRedirect();

        $place = Place::query()->firstOrFail();

        $this->assertSame(PublishStatus::Draft, $place->status);
        $this->assertSame($admin->id, $place->created_by);
        $this->assertSame('pura-uji-tirta', $place->slug);
    }

    public function test_store_is_audited_with_the_actor(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $this->actingAs($admin)->post('/admin/places', $this->placePayload());

        $log = AuditLog::query()->where('action', 'place.created')->firstOrFail();

        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('place', $log->entity_type);
        $this->assertNotNull($log->ip_hash);
    }

    public function test_update_records_only_changed_fields(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $place = Place::factory()->create(['name' => 'Nama lama', 'slug' => 'nama-lama']);

        $this->actingAs($admin)->put("/admin/places/{$place->id}", $this->placePayload([
            'region_id' => $place->region_id,
            'name' => 'Nama baru',
            'slug' => 'nama-lama',
            'summary' => $place->summary,
            'lat' => (string) $place->lat,
            'lng' => (string) $place->lng,
        ]))->assertRedirect();

        $log = AuditLog::query()->where('action', 'place.updated')->firstOrFail();

        $this->assertSame('Nama lama', $log->before_json['name']);
        $this->assertSame('Nama baru', $log->after_json['name']);
        $this->assertArrayNotHasKey('summary', $log->after_json);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/places', $this->placePayload(['lat' => '120']))
            ->assertSessionHasErrors('lat');

        $this->assertSame(0, Place::count());
    }

    public function test_half_filled_hours_are_rejected(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/places', $this->placePayload(['hours' => ['mon' => ['open' => '08:00', 'close' => '']]]))
            ->assertSessionHasErrors('hours.mon.close');
    }

    public function test_hours_are_normalised_and_status_must_match_data(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post('/admin/places', $this->placePayload([
            'hours' => ['mon' => ['open' => '08:00', 'close' => '17:00'], 'sun' => ['closed' => '1']],
            'opening_hours_status' => 'verified',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['mon' => ['open' => '08:00', 'close' => '17:00'], 'sun' => ['closed' => true]],
            Place::query()->firstOrFail()->opening_hours,
        );

        $this->actingAs($admin)->post('/admin/places', $this->placePayload([
            'name' => 'Tempat Lain',
            'opening_hours_status' => 'verified',
        ]))->assertSessionHasErrors('opening_hours_status');
    }

    public function test_verified_status_requires_a_verification_date(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/places', $this->placePayload(['verification_status' => 'verified']))
            ->assertSessionHasErrors('verified_at');
    }

    public function test_slug_is_locked_after_publishing(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $place = Place::factory()->published()->create(['slug' => 'slug-tetap']);

        $this->actingAs($admin)->put("/admin/places/{$place->id}", $this->placePayload([
            'region_id' => $place->region_id,
            'name' => $place->name,
            'slug' => 'slug-baru',
            'summary' => $place->summary,
            'lat' => (string) $place->lat,
            'lng' => (string) $place->lng,
            'verification_status' => 'verified',
            'verified_at' => now()->subDay()->toDateString(),
        ]));

        $this->assertSame('slug-tetap', $place->fresh()->slug);
    }

    public function test_slug_must_be_unique(): void
    {
        Place::factory()->create(['slug' => 'pura-uji-tirta']);

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/places', $this->placePayload())
            ->assertSessionHasErrors('slug');
    }

    public function test_published_place_cannot_be_deleted(): void
    {
        $place = Place::factory()->published()->create();

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->delete("/admin/places/{$place->id}")
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted($place);
    }

    public function test_place_used_in_a_plan_cannot_be_deleted(): void
    {
        $place = Place::factory()->create();
        $plan = $this->makeUser()->tripPlans()->create([
            'title' => 'Uji', 'creation_mode' => 'manual', 'days_count' => 1,
        ]);
        $day = $plan->days()->create(['day_number' => 1, 'sort_order' => 1]);
        $day->items()->create(['place_id' => $place->id, 'item_source' => 'manual', 'sort_order' => 1]);

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->delete("/admin/places/{$place->id}")
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, TripPlanItem::count());
        $this->assertNotSoftDeleted($place);
    }

    public function test_draft_place_can_be_deleted_and_is_audited(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->delete("/admin/places/{$place->id}")
            ->assertRedirect(route('admin.places.index'));

        $this->assertSoftDeleted($place);
        $this->assertTrue(AuditLog::query()->where('action', 'place.deleted')->exists());
    }
}

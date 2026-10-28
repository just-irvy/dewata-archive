<?php

namespace Tests\Feature;

use App\Enums\PriceBasis;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class BulkPublishTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private function bulk(string $type, array $payload)
    {
        return $this->actingAs($this->makeUser(UserRole::Admin))->from("/admin/{$type}")->post("/admin/{$type}/bulk-status", $payload);
    }

    public function test_selected_ready_drafts_are_published_and_the_rest_is_reported(): void
    {
        $a = Place::factory()->ready()->create(['name' => 'Siap A']);
        $b = Place::factory()->ready()->create(['name' => 'Siap B']);
        $c = Place::factory()->create(['name' => 'Belum Siap']);

        $this->bulk('places', ['scope' => 'selected', 'ids' => [$a->id, $b->id, $c->id]])->assertRedirect('/admin/places');

        $this->assertSame(PublishStatus::Published, $a->fresh()->status);
        $this->assertSame(PublishStatus::Published, $b->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $c->fresh()->status);

        $report = session('bulk_report');
        $this->assertSame(2, $report['published']);
        $this->assertSame(1, $report['blocked']);
        $this->assertSame(['Belum Siap'], $report['samples']);
        $this->assertArrayHasKey('Tautan sumber belum diisi.', $report['reasons']);
        $this->assertSame(2, AuditLog::where('action', 'place.published')->count());
    }

    public function test_the_ready_scope_publishes_every_ready_draft_and_never_touches_other_statuses(): void
    {
        $draftA = Place::factory()->ready()->create();
        $draftB = Place::factory()->ready()->create();
        $published = Place::factory()->published()->create();
        $archived = Place::factory()->published()->create();
        $archived->forceFill(['status' => PublishStatus::Archived])->save();

        $this->bulk('places', ['scope' => 'ready']);

        $this->assertSame(PublishStatus::Published, $draftA->fresh()->status);
        $this->assertSame(PublishStatus::Published, $draftB->fresh()->status);
        $this->assertSame(PublishStatus::Archived, $archived->fresh()->status);
        $this->assertSame(2, session('bulk_report')['published']);
        $this->assertSame(0, session('bulk_report')['blocked']);
    }

    public function test_blockers_are_grouped_by_reason_instead_of_listed_per_record(): void
    {
        Place::factory()->count(3)->create();

        $this->bulk('places', ['scope' => 'ready']);

        $reasons = session('bulk_report')['reasons'];
        $this->assertSame(3, $reasons['Tautan sumber belum diisi.']);
        $this->assertSame(3, $reasons['Tanggal verifikasi belum diisi.']);
        $this->assertSame(0, session('bulk_report')['published']);
    }

    public function test_the_ready_scope_respects_the_region_filter_and_the_name_filter(): void
    {
        $regionA = Region::factory()->create();
        $inRegion = Place::factory()->ready()->create(['region_id' => $regionA->id, 'name' => 'Pura Dalam']);
        $other = Place::factory()->ready()->create(['name' => 'Pura Luar']);
        $differentName = Place::factory()->ready()->create(['region_id' => $regionA->id, 'name' => 'Sawah']);

        $this->bulk('places', ['scope' => 'ready', 'region' => $regionA->id, 'q' => 'Pura']);

        $this->assertSame(PublishStatus::Published, $inRegion->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $other->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $differentName->fresh()->status);
    }

    public function test_the_per_click_limit_leaves_the_remainder_for_another_run(): void
    {
        config(['archive.bulk_publish_limit' => 2]);
        Place::factory()->ready()->count(3)->create();

        $this->bulk('places', ['scope' => 'ready']);

        $this->assertSame(2, session('bulk_report')['published']);
        $this->assertSame(1, session('bulk_report')['remaining']);

        $this->bulk('places', ['scope' => 'ready']);

        $this->assertSame(1, session('bulk_report')['published']);
        $this->assertSame(0, Place::where('status', 'draft')->count());
    }

    public function test_selected_ids_that_are_not_drafts_are_ignored(): void
    {
        $published = Place::factory()->published()->create();
        $before = $published->fresh()->updated_at;

        $this->bulk('places', ['scope' => 'selected', 'ids' => [$published->id]]);

        $this->assertSame(0, session('bulk_report')['published']);
        $this->assertSame(0, session('bulk_report')['blocked']);
        $this->assertTrue($before->equalTo($published->fresh()->updated_at));
    }

    public function test_hotels_follow_their_own_readiness_rules(): void
    {
        $withoutPrice = Hotel::factory()->ready()->create();
        $unverifiedPrice = Hotel::factory()->ready()->create(['price_min' => 400000, 'price_basis' => PriceBasis::Curated]);

        $this->bulk('hotels', ['scope' => 'ready']);

        $this->assertSame(PublishStatus::Published, $withoutPrice->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $unverifiedPrice->fresh()->status);
        $this->assertArrayHasKey('Harga terisi tetapi status verifikasi harga belum diketahui.', session('bulk_report')['reasons']);
    }

    public function test_only_admins_can_publish_in_bulk(): void
    {
        $place = Place::factory()->ready()->create();

        $this->post('/admin/places/bulk-status', ['scope' => 'ready'])->assertRedirect(route('login'));
        $this->actingAs($this->makeUser())->post('/admin/places/bulk-status', ['scope' => 'ready'])->assertForbidden();

        $this->assertSame(PublishStatus::Draft, $place->fresh()->status);
    }

    public function test_input_is_validated(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post('/admin/places/bulk-status', ['scope' => 'semua'])->assertSessionHasErrors('scope');
        $this->actingAs($admin)->post('/admin/places/bulk-status', ['scope' => 'selected'])->assertSessionHasErrors('ids');
        $this->actingAs($admin)->post('/admin/bogus/bulk-status', ['scope' => 'ready'])->assertNotFound();
    }

    public function test_the_list_shows_readiness_and_the_report_after_a_bulk_run(): void
    {
        Place::factory()->ready()->create(['name' => 'Tempat Siap']);
        Place::factory()->create(['name' => 'Tempat Kurang']);
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->get('/admin/places')->assertOk()
            ->assertSee('Siap diterbitkan')
            ->assertSee('Kurang');

        $this->actingAs($admin)->from('/admin/places')->post('/admin/places/bulk-status', ['scope' => 'ready']);

        $this->actingAs($admin)->get('/admin/places')->assertOk()
            ->assertSee('Hasil terbit massal')
            ->assertSee('Diterbitkan')
            ->assertSee('Penyebab yang paling sering');
    }
}

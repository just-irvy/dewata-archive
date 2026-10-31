<?php

namespace Tests\Feature;

use App\Enums\ItemSource;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\TripPlan;
use App\Models\User;
use App\Planner\GuestDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\BuildsPlannerData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

/** Editor itinerary bersama untuk kedua mode (FILE 10) dan uji akses lintas akun (FILE 19). */
class PlanEditorTest extends TestCase
{
    use BuildsArchiveData;
    use BuildsPlannerData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->owner = $this->makeUser();
    }

    private function manualPlan(int $days = 2): TripPlan
    {
        $this->actingAs($this->owner)->post('/planner/manual', ['title' => 'Arsip uji', 'days_count' => $days, 'transport_mode' => 'car']);

        return TripPlan::query()->latest('id')->first();
    }

    private function place(string $name): Place
    {
        return Place::query()->where('name', $name)->firstOrFail();
    }

    private function add(TripPlan $plan, int $dayNumber, string $name)
    {
        return $this->actingAs($this->owner)->post(route('planner.plans.items.store', $plan->id), [
            'day_id' => $plan->days()->where('day_number', $dayNumber)->value('id'),
            'place_id' => $this->place($name)->id,
        ]);
    }

    private function order(TripPlan $plan, int $dayNumber): array
    {
        return $plan->days()->where('day_number', $dayNumber)->first()
            ->items()->with('place')->get()->pluck('place.name')->all();
    }

    public function test_manual_add_reorder_move_and_remove_keep_order_contiguous(): void
    {
        $plan = $this->manualPlan();
        $this->add($plan, 1, 'Pura Tirta')->assertRedirect();
        $this->add($plan, 1, 'Goa Gajah');
        $this->add($plan, 1, 'Pura Saraswati');

        $this->assertSame(['Pura Tirta', 'Goa Gajah', 'Pura Saraswati'], $this->order($plan, 1));
        $this->assertTrue($plan->items()->get()->every(fn ($i) => $i->item_source === ItemSource::Manual));

        $goa = $plan->items()->where('place_id', $this->place('Goa Gajah')->id)->first();
        $this->post(route('planner.plans.items.move', [$plan->id, $goa->id]), ['direction' => 'up']);
        $this->assertSame(['Goa Gajah', 'Pura Tirta', 'Pura Saraswati'], $this->order($plan, 1));

        $this->post(route('planner.plans.items.move', [$plan->id, $goa->id]), ['day_id' => $plan->days()->where('day_number', 2)->value('id')]);
        $this->assertSame(['Pura Tirta', 'Pura Saraswati'], $this->order($plan, 1));
        $this->assertSame(['Goa Gajah'], $this->order($plan, 2));

        $tirta = $plan->items()->where('place_id', $this->place('Pura Tirta')->id)->first();
        $this->post(route('planner.plans.items.move', [$plan->id, $tirta->id]), ['day_id' => $tirta->trip_plan_day_id, 'position' => 2]);
        $this->assertSame(['Pura Saraswati', 'Pura Tirta'], $this->order($plan, 1));

        $this->delete(route('planner.plans.items.destroy', [$plan->id, $tirta->id]))->assertRedirect();
        $this->assertSame(['Pura Saraswati'], $this->order($plan, 1));
        $this->assertSame([1], $plan->days()->where('day_number', 1)->first()->items()->pluck('sort_order')->all());
    }

    public function test_only_published_places_can_be_added_and_never_twice(): void
    {
        $plan = $this->manualPlan();
        $draft = $this->spot($this->gianyar, 'Pura Rahasia', -8.42, 115.31, [], [], PublishStatus::Draft);

        $this->actingAs($this->owner)->post(route('planner.plans.items.store', $plan->id), [
            'day_id' => $plan->days()->first()->id, 'place_id' => $draft->id,
        ])->assertSessionHasErrors('place_id');

        $this->add($plan, 1, 'Pura Tirta')->assertSessionHasNoErrors();
        $this->add($plan, 2, 'Pura Tirta')->assertSessionHasErrors('place_id');
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_a_day_cannot_exceed_the_route_limit(): void
    {
        config(['planner.max_items_per_day' => 2]);
        $plan = $this->manualPlan(1);

        $this->add($plan, 1, 'Pura Tirta');
        $this->add($plan, 1, 'Goa Gajah');
        $this->add($plan, 1, 'Pura Saraswati')->assertSessionHasErrors('day');

        $this->assertSame(2, $plan->items()->count());
    }

    public function test_day_ids_from_another_plan_are_rejected(): void
    {
        $plan = $this->manualPlan();
        $other = $this->manualPlan();

        $this->actingAs($this->owner)->post(route('planner.plans.items.store', $plan->id), [
            'day_id' => $other->days()->first()->id, 'place_id' => $this->place('Pura Tirta')->id,
        ])->assertSessionHasErrors('day_id');
        $this->assertSame(0, $plan->items()->count() + $other->items()->count());
    }

    public function test_days_can_be_added_and_removed_within_bounds(): void
    {
        $plan = $this->manualPlan(1);
        $this->actingAs($this->owner)->delete(route('planner.plans.days.destroy', [$plan->id, $plan->days()->first()->id]))->assertSessionHasErrors('day');

        $this->post(route('planner.plans.days.store', $plan->id))->assertRedirect();
        $this->add($plan, 2, 'Goa Gajah');
        $this->assertSame(2, $plan->fresh()->days_count);

        $this->delete(route('planner.plans.days.destroy', [$plan->id, $plan->days()->first()->id]));
        $plan->refresh();
        $this->assertSame(1, $plan->days_count);
        $this->assertSame(1, $plan->days()->first()->day_number);
        $this->assertSame(['Goa Gajah'], $this->order($plan, 1));

        config(['planner.max_days' => 1]);
        $this->post(route('planner.plans.days.store', $plan->id))->assertSessionHasErrors('day');
    }

    public function test_note_duration_and_start_time_save_as_json(): void
    {
        $plan = $this->manualPlan();
        $this->add($plan, 1, 'Pura Tirta');
        $item = $plan->items()->first();

        $this->actingAs($this->owner)->patchJson(route('planner.plans.items.update', [$plan->id, $item->id]), [
            'note' => 'Bawa kain', 'duration_minutes' => 75, 'start_time' => '08:30',
        ])->assertOk()->assertJson(['ok' => true]);

        $item->refresh();
        $this->assertSame('Bawa kain', $item->note);
        $this->assertSame(75, (int) $item->duration_minutes);

        $this->patchJson(route('planner.plans.items.update', [$plan->id, $item->id]), ['duration_minutes' => 2000])
            ->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
    }

    public function test_day_hotel_becomes_the_route_origin(): void
    {
        $plan = $this->manualPlan(1);
        $this->add($plan, 1, 'Pura Tirta');
        $hotel = Hotel::factory()->published()->create(['region_id' => $this->gianyar->id, 'name' => 'Penginapan Sawah']);
        $draftHotel = Hotel::factory()->create(['region_id' => $this->gianyar->id]);
        $day = $plan->days()->first();

        $this->actingAs($this->owner)->patch(route('planner.plans.days.update', [$plan->id, $day->id]), ['hotel_id' => $draftHotel->id])
            ->assertSessionHasErrors('hotel_id');

        $this->patch(route('planner.plans.days.update', [$plan->id, $day->id]), ['hotel_id' => $hotel->id, 'return_to_hotel' => '1', 'title' => 'Pagi di pura']);
        $day->refresh();
        $this->assertSame($hotel->id, $day->hotel_id);
        $this->assertTrue($day->return_to_hotel);

        config(['archive.maps.browser_key' => 'test-key']);
        $this->get(route('account.plans.show', $plan->id))->assertOk()
            ->assertSee('Penginapan Sawah')
            ->assertSee('D1·1')
            ->assertSee('returnToOrigin', false);
    }

    public function test_public_transport_is_listed_but_never_routed(): void
    {
        config(['archive.maps.browser_key' => 'test-key']);
        $this->actingAs($this->owner)->post('/planner/manual', ['title' => 'Bus', 'days_count' => 1, 'transport_mode' => 'public']);
        $plan = TripPlan::query()->sole();
        $this->add($plan, 1, 'Pura Tirta');
        $this->add($plan, 1, 'Goa Gajah');

        $this->get(route('account.plans.show', $plan->id))->assertOk()
            ->assertSee('Transportasi umum tidak dirutekan');
    }

    public function test_recommended_plan_items_stay_editable_and_days_can_be_regenerated(): void
    {
        $this->actingAs($this->owner)->post('/planner/recommend', $this->wizard(['days_count' => 1, 'region_ids' => [$this->gianyar->id], 'pace' => 'relaxed']));
        $plan = TripPlan::query()->sole();
        $day = $plan->days()->first();
        $first = $day->items()->first();
        $before = $day->items()->count();
        $this->assertSame((int) config('planner.pace.relaxed.max_stops'), $before);

        $this->delete(route('planner.plans.items.destroy', [$plan->id, $first->id]))->assertSessionHasNoErrors();
        $this->assertSame($before - 1, $day->items()->count());

        $this->add($plan, 1, 'Air Terjun Tegenungan');
        $this->assertSame(ItemSource::Manual, $day->items()->get()->last()->item_source);

        $this->post(route('planner.plans.days.regenerate', [$plan->id, $day->id]))->assertRedirect();
        $items = $day->items()->get();
        $this->assertNotEmpty($items);
        $this->assertTrue($items->every(fn ($i) => $i->item_source === ItemSource::Recommended));
    }

    public function test_manual_plans_cannot_be_regenerated(): void
    {
        $plan = $this->manualPlan(1);
        $this->actingAs($this->owner)->post(route('planner.plans.days.regenerate', [$plan->id, $plan->days()->first()->id]))
            ->assertSessionHasErrors('day');
    }

    public function test_replace_keeps_position_and_explains_the_choice(): void
    {
        $this->actingAs($this->owner)->post('/planner/recommend', $this->wizard(['days_count' => 1, 'region_ids' => [$this->gianyar->id], 'pace' => 'relaxed', 'themes' => ['pura-arsitektur']]));
        $plan = TripPlan::query()->sole();
        $item = $plan->items()->orderBy('sort_order')->first();
        $used = $plan->items()->pluck('place_id')->all();
        $spare = Place::query()->published()->where('region_id', $this->gianyar->id)->whereNotIn('id', $used)
            ->whereHas('tags', fn ($q) => $q->where('slug', 'pura-arsitektur'))->firstOrFail();

        $this->post(route('planner.plans.items.replace', [$plan->id, $item->id]), ['place_id' => $spare->id])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame($spare->id, $item->place_id);
        $this->assertSame(1, (int) $item->sort_order);
        $this->assertStringStartsWith('Dipilih karena', $item->recommendation_reason);
    }

    public function test_other_users_get_404_on_every_editor_endpoint(): void
    {
        $plan = $this->manualPlan();
        $this->add($plan, 1, 'Pura Tirta');
        $day = $plan->days()->first();
        $item = $plan->items()->first();
        $stranger = $this->makeUser();
        $admin = $this->makeUser(UserRole::SuperAdmin);

        foreach ([$stranger, $admin] as $user) {
            $this->actingAs($user);
            $this->get(route('account.plans.show', $plan->id))->assertNotFound();
            $this->patch(route('planner.plans.update', $plan->id), ['title' => 'Diretas'])->assertNotFound();
            $this->post(route('planner.plans.days.store', $plan->id))->assertNotFound();
            $this->patch(route('planner.plans.days.update', [$plan->id, $day->id]), ['title' => 'x'])->assertNotFound();
            $this->post(route('planner.plans.items.store', $plan->id), ['day_id' => $day->id, 'place_id' => $this->place('Goa Gajah')->id])->assertNotFound();
            $this->patch(route('planner.plans.items.update', [$plan->id, $item->id]), ['note' => 'x'])->assertNotFound();
            $this->post(route('planner.plans.items.move', [$plan->id, $item->id]), ['direction' => 'down'])->assertNotFound();
            $this->delete(route('planner.plans.items.destroy', [$plan->id, $item->id]))->assertNotFound();
            $this->delete(route('planner.plans.destroy', $plan->id))->assertNotFound();
        }

        $this->assertSame('Arsip uji', $plan->fresh()->title);
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_an_item_id_from_another_plan_is_404(): void
    {
        $mine = $this->manualPlan();
        $this->add($mine, 1, 'Pura Tirta');

        $theirs = User::factory()->create();
        $other = $theirs->tripPlans()->create(['title' => 'Lain', 'creation_mode' => 'manual', 'days_count' => 1]);
        $otherDay = $other->days()->create(['day_number' => 1, 'sort_order' => 1]);
        $foreign = $otherDay->items()->create(['place_id' => $this->place('Goa Gajah')->id, 'item_source' => 'manual', 'sort_order' => 1]);

        $this->actingAs($this->owner)->delete(route('planner.plans.items.destroy', [$mine->id, $foreign->id]))->assertNotFound();
        $this->assertDatabaseHas('trip_plan_items', ['id' => $foreign->id]);
    }

    public function test_guest_editing_works_through_the_cookie_only(): void
    {
        $response = $this->post('/planner/manual', ['title' => 'Tamu', 'days_count' => 1]);
        $cookie = $this->draftCookie($response);
        $plan = TripPlan::query()->sole();

        $this->post(route('planner.plans.items.store', $plan->id), ['day_id' => $plan->days()->first()->id, 'place_id' => $this->place('Pura Tirta')->id])
            ->assertNotFound();

        $this->withCookie(GuestDrafts::COOKIE, $cookie)
            ->post(route('planner.plans.items.store', $plan->id), ['day_id' => $plan->days()->first()->id, 'place_id' => $this->place('Pura Tirta')->id])
            ->assertRedirect();
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_summary_and_print_tools_are_rendered(): void
    {
        $plan = $this->manualPlan(1);
        $this->add($plan, 1, 'Pura Tirta');

        $this->get(route('account.plans.show', $plan->id))->assertOk()
            ->assertSee('Salin ringkasan')
            ->assertSee('Cetak')
            ->assertSee('1. Pura Tirta (Gianyar)', false)
            ->assertSee('Cek jam buka sebelum berkunjung');
    }

    public function test_catalog_search_inside_the_editor_marks_places_already_in_the_plan(): void
    {
        $plan = $this->manualPlan(1);
        $this->add($plan, 1, 'Pura Tirta');

        $this->get(route('account.plans.show', ['plan' => $plan->id, 'cari' => 'Pura']))->assertOk()
            ->assertSee('Sudah ada di Hari 1')
            ->assertSee('Pura Saraswati')
            ->assertSee('Tambahkan');
    }

    public function test_stay_matches_show_nightly_ranges_without_any_trip_total(): void
    {
        Hotel::factory()->published()->create([
            'region_id' => $this->gianyar->id, 'name' => 'Rumah Tamu Ubud', 'price_min' => 400000, 'price_max' => 600000,
            'price_basis' => 'curated', 'price_status' => 'verified',
        ]);
        Hotel::factory()->published()->create([
            'region_id' => $this->gianyar->id, 'name' => 'Resor Mahal', 'price_min' => 5000000, 'price_max' => 9000000,
            'price_basis' => 'provider', 'price_status' => 'verified',
        ]);

        $this->actingAs($this->owner)->post('/planner/recommend', $this->wizard([
            'region_ids' => [$this->gianyar->id], 'want_hotel' => '1', 'price_min' => 300000, 'price_max' => 800000,
        ]));
        $plan = TripPlan::query()->sole();

        $this->get(route('account.plans.show', $plan->id))->assertOk()
            ->assertSee('Penginapan yang cocok')
            ->assertSee('Rumah Tamu Ubud')
            ->assertDontSee('>Resor Mahal</a>', false)
            ->assertSee('per kamar per malam')
            ->assertDontSee('Total biaya')
            ->assertDontSee('total perjalanan');

        $this->post(route('planner.plans.hotel', $plan->id), ['hotel_id' => Hotel::query()->where('name', 'Rumah Tamu Ubud')->value('id')]);
        $this->assertTrue($plan->days()->get()->every(fn ($d) => $d->hotel_id !== null));
    }
}

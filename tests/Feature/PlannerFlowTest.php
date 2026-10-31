<?php

namespace Tests\Feature;

use App\Enums\CreationMode;
use App\Enums\ItemSource;
use App\Enums\TripPlanStatus;
use App\Models\TripPlan;
use App\Planner\GuestDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\BuildsPlannerData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

/** Alur planner kedua mode, draf tamu, dan penyimpanan ke akun (FILE 10 dan FILE 19). */
class PlannerFlowTest extends TestCase
{
    use BuildsArchiveData;
    use BuildsPlannerData;
    use RefreshDatabase;
    use WithoutViteAssets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_mode_selector_offers_both_modes_without_an_account(): void
    {
        $this->get('/planner')->assertOk()
            ->assertSee('Dapatkan rekomendasi')
            ->assertSee('Susun sendiri')
            ->assertSee(route('planner.recommend'), false)
            ->assertSee(route('planner.manual'), false);
    }

    public function test_wizard_has_no_trip_budget_field(): void
    {
        $html = $this->get('/planner/recommend')->assertOk()->assertSee('Langkah wizard')->getContent();

        $this->assertStringNotContainsString('name="budget', $html);
        $this->assertStringNotContainsString('name="total', $html);
        // Satu-satunya input uang adalah kisaran harga per malam.
        $this->assertStringContainsString('name="price_min"', $html);
        $this->assertStringContainsString('per malam', $html);
    }

    public function test_guest_generates_a_recommended_draft_with_a_hashed_token(): void
    {
        $response = $this->post('/planner/recommend', $this->wizard(['region_ids' => [$this->gianyar->id]]));

        $plan = TripPlan::query()->sole();
        $response->assertRedirect(route('planner.drafts.show', $plan->id));

        $this->assertNull($plan->user_id);
        $this->assertSame(CreationMode::Recommended, $plan->creation_mode);
        $this->assertSame(64, strlen($plan->guest_token_hash));
        $this->assertTrue($plan->expires_at->isFuture());
        $this->assertSame(config('planner.recommendation_version'), $plan->recommendation_version);
        $this->assertSame(['pura-arsitektur', 'alam'], $plan->planner_answers['themes']);
        $this->assertCount(2, $plan->days);
        $this->assertGreaterThan(0, $plan->items()->count());
        $this->assertTrue($plan->items()->get()->every(fn ($i) => $i->item_source === ItemSource::Recommended && filled($i->recommendation_reason)));

        $tokens = json_decode($this->draftCookie($response), true);
        $this->assertSame(GuestDrafts::hash($tokens[$plan->id]), $plan->guest_token_hash);

        $this->withCookie(GuestDrafts::COOKIE, $this->draftCookie($response))
            ->get(route('planner.drafts.show', $plan->id))
            ->assertOk()
            ->assertSee('Draf tamu')
            ->assertSee('Dipilih karena')
            ->assertSee('Simpan ke akun');
    }

    public function test_guest_draft_is_not_reachable_without_its_cookie(): void
    {
        $this->post('/planner/recommend', $this->wizard());
        $plan = TripPlan::query()->sole();

        $this->get(route('planner.drafts.show', $plan->id))->assertNotFound();
        $this->withCookie(GuestDrafts::COOKIE, json_encode([$plan->id => str_repeat('a', 48)]))
            ->get(route('planner.drafts.show', $plan->id))->assertNotFound();
        $this->actingAs($this->makeUser())->get(route('account.plans.show', $plan->id))->assertNotFound();
    }

    public function test_expired_guest_draft_is_gone_and_purged(): void
    {
        $response = $this->post('/planner/recommend', $this->wizard());
        $plan = TripPlan::query()->sole();
        $plan->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withCookie(GuestDrafts::COOKIE, $this->draftCookie($response))
            ->get(route('planner.drafts.show', $plan->id))->assertNotFound();

        $this->artisan('archive:purge-guest-drafts')->assertSuccessful();
        $this->assertDatabaseCount('trip_plans', 0);
        $this->assertDatabaseCount('trip_plan_items', 0);
    }

    public function test_wizard_validation(): void
    {
        $this->post('/planner/recommend', $this->wizard(['themes' => []]))->assertSessionHasErrors('themes');
        $this->post('/planner/recommend', $this->wizard(['themes' => [], 'surprise' => '1']))->assertSessionHasNoErrors();
        $this->post('/planner/recommend', $this->wizard(['days_count' => 15]))->assertSessionHasErrors('days_count');
        $this->post('/planner/recommend', $this->wizard(['themes' => ['bukan-tema']]))->assertSessionHasErrors('themes.0');
        $this->post('/planner/recommend', $this->wizard(['want_hotel' => '1', 'price_min' => 900000, 'price_max' => 100000]))->assertSessionHasErrors('price_max');
        $this->post('/planner/recommend', $this->wizard(['start_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('start_date');
    }

    public function test_validation_errors_reopen_the_right_step_and_keep_answers(): void
    {
        $this->from('/planner/recommend')->followingRedirects()
            ->post('/planner/recommend', $this->wizard(['themes' => [], 'days_count' => 4]))
            ->assertOk()
            ->assertSee('\\u0022initialStep\\u0022:2', false)
            ->assertSee('\\u0022days_count\\u0022:\\u00224\\u0022', false);
    }

    public function test_no_match_returns_to_the_wizard_without_creating_an_empty_plan(): void
    {
        $this->from('/planner/recommend')
            ->post('/planner/recommend', $this->wizard(['region_ids' => [$this->buleleng->id], 'themes' => ['alam'], 'mobility' => ['step_free']]))
            ->assertRedirect('/planner/recommend')
            ->assertSessionHasErrors('generate');

        $this->assertDatabaseCount('trip_plans', 0);
    }

    public function test_signed_in_user_plan_is_owned_immediately(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/planner/recommend', $this->wizard())
            ->assertRedirect(route('account.plans.show', TripPlan::query()->sole()->id));

        $plan = TripPlan::query()->sole();
        $this->assertSame($user->id, $plan->user_id);
        $this->assertNull($plan->guest_token_hash);
        $this->assertNull($plan->expires_at);

        $this->actingAs($user)->get(route('account.plans.index'))->assertOk()->assertSee($plan->title);
        $this->actingAs($user)->get(route('account.plans.show', $plan->id))->assertOk();
    }

    public function test_manual_mode_creates_empty_days_and_never_inserts_places(): void
    {
        $response = $this->post('/planner/manual', ['title' => 'Utara pelan', 'days_count' => 3, 'transport_mode' => 'motorbike']);
        $plan = TripPlan::query()->sole();

        $response->assertRedirect(route('planner.drafts.show', $plan->id));
        $this->assertSame(CreationMode::Manual, $plan->creation_mode);
        $this->assertNull($plan->planner_answers);
        $this->assertNull($plan->generated_at);
        $this->assertCount(3, $plan->days);
        $this->assertSame(0, $plan->items()->count());

        $this->withCookie(GuestDrafts::COOKIE, $this->draftCookie($response))
            ->get(route('planner.drafts.show', $plan->id))->assertOk()
            ->assertSee('Hari ini masih kosong')
            ->assertDontSee('Susun ulang hari ini');
    }

    public function test_manual_validation(): void
    {
        $this->post('/planner/manual', ['title' => '', 'days_count' => 0])->assertSessionHasErrors(['title', 'days_count']);
        $this->post('/planner/manual', ['title' => 'x', 'days_count' => 2, 'transport_mode' => 'jet'])->assertSessionHasErrors('transport_mode');
    }

    public function test_start_date_gives_each_day_a_date(): void
    {
        $start = now()->addWeek()->toDateString();
        $this->post('/planner/manual', ['title' => 'Bertanggal', 'days_count' => 3, 'start_date' => $start]);

        $days = TripPlan::query()->sole()->days;
        $this->assertSame($start, $days[0]->date->toDateString());
        $this->assertSame(now()->addWeek()->addDays(2)->toDateString(), $days[2]->date->toDateString());
    }

    public function test_guest_save_sends_to_login_and_claims_the_draft_after_login(): void
    {
        $user = $this->makeUser();
        $created = $this->post('/planner/recommend', $this->wizard());
        $cookie = $this->draftCookie($created);
        $plan = TripPlan::query()->sole();

        // Satu tempat ditarik dari publikasi sebelum login: tidak boleh ikut dipindah.
        $removed = $plan->items()->first()->place;
        $removed->forceFill(['status' => 'archived'])->save();

        $this->withCookie(GuestDrafts::COOKIE, $cookie)
            ->post(route('planner.plans.save', $plan->id))
            ->assertRedirect(route('login'));

        $this->withCookie(GuestDrafts::COOKIE, $cookie)
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.plans.show', $plan->id));

        $plan->refresh();
        $this->assertSame($user->id, $plan->user_id);
        $this->assertNull($plan->guest_token_hash);
        $this->assertNull($plan->expires_at);
        $this->assertSame(TripPlanStatus::Saved, $plan->status);
        $this->assertFalse($plan->items()->where('place_id', $removed->id)->exists());

        $this->get(route('account.plans.show', $plan->id))->assertOk();
    }

    public function test_login_without_a_draft_cookie_claims_nothing(): void
    {
        $user = $this->makeUser();
        $this->post('/planner/recommend', $this->wizard());
        $plan = TripPlan::query()->sole();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNull($plan->fresh()->user_id);
    }

    public function test_owner_can_delete_their_plan_and_others_cannot(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post('/planner/manual', ['title' => 'Milik saya', 'days_count' => 1]);
        $plan = TripPlan::query()->sole();

        $this->actingAs($this->makeUser())->delete(route('planner.plans.destroy', $plan->id))->assertNotFound();
        $this->assertDatabaseHas('trip_plans', ['id' => $plan->id]);

        $this->actingAs($owner)->delete(route('planner.plans.destroy', $plan->id))->assertRedirect(route('account.plans.index'));
        $this->assertDatabaseMissing('trip_plans', ['id' => $plan->id]);
    }

    public function test_editing_preferences_prefills_the_wizard_from_saved_answers(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/planner/recommend', $this->wizard(['days_count' => 2, 'pace' => 'relaxed']));
        $plan = TripPlan::query()->sole();

        $this->actingAs($user)->get(route('planner.recommend', ['dari' => $plan->id]))->assertOk()
            ->assertSee('\\u0022pace\\u0022:\\u0022relaxed\\u0022', false);

        $this->actingAs($this->makeUser())->get(route('planner.recommend', ['dari' => $plan->id]))->assertNotFound();
    }

    public function test_plan_creation_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/planner/manual', ['title' => 'Uji '.$i, 'days_count' => 1]);
        }

        $this->post('/planner/manual', ['title' => 'Satu lagi', 'days_count' => 1])->assertStatus(429);
    }

    public function test_guest_draft_limit_drops_the_oldest(): void
    {
        $cookie = null;
        $max = config('planner.guest_max_drafts');

        for ($i = 0; $i <= $max; $i++) {
            $request = $cookie ? $this->withCookie(GuestDrafts::COOKIE, $cookie) : $this;
            $response = $request->post('/planner/manual', ['title' => 'Draf '.$i, 'days_count' => 1]);
            $cookie = $this->draftCookie($response);
        }

        $this->assertCount($max, json_decode($cookie, true));
        $this->assertDatabaseCount('trip_plans', $max);
        $this->assertDatabaseMissing('trip_plans', ['title' => 'Draf 0']);
    }
}

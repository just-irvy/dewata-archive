<?php

namespace Tests\Feature;

use App\Enums\CreationMode;
use App\Enums\UserRole;
use App\Models\TripPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\BuildsArchiveData;
use Tests\TestCase;

/** Uji IDOR dengan dua akun (FILE 19, jenis pengujian security). */
class TripPlanOwnershipTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;

    private function planFor($user): TripPlan
    {
        return $user->tripPlans()->create([
            'title' => 'Tiga hari di Gianyar',
            'creation_mode' => CreationMode::Manual,
            'days_count' => 3,
        ]);
    }

    public function test_owner_can_view_update_and_delete(): void
    {
        $owner = $this->makeUser();
        $plan = $this->planFor($owner);

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $plan), $ability);
        }
    }

    public function test_other_user_is_denied_everything(): void
    {
        $plan = $this->planFor($this->makeUser());
        $stranger = $this->makeUser();

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($stranger)->allows($ability, $plan), $ability);
        }
    }

    public function test_admin_gets_no_special_access_to_user_plans(): void
    {
        $plan = $this->planFor($this->makeUser());
        $admin = $this->makeUser(UserRole::SuperAdmin);

        $this->assertFalse(Gate::forUser($admin)->allows('view', $plan));
    }

    public function test_scoped_query_never_returns_another_users_plan(): void
    {
        $plan = $this->planFor($this->makeUser());
        $stranger = $this->makeUser();

        $this->assertNull(TripPlan::query()->ownedBy($stranger)->find($plan->id));
    }

    public function test_owner_columns_are_not_mass_assignable(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();

        $plan = $owner->tripPlans()->create([
            'title' => 'Uji',
            'creation_mode' => CreationMode::Manual,
            'days_count' => 1,
            'user_id' => $stranger->id,
            'status' => 'saved',
        ]);

        $this->assertSame($owner->id, $plan->fresh()->user_id);
        $this->assertSame('draft', $plan->fresh()->status->value);
    }

    public function test_expired_guest_drafts_are_excluded(): void
    {
        $live = new TripPlan(['title' => 'Aktif', 'creation_mode' => CreationMode::Manual, 'days_count' => 1]);
        $live->forceFill(['guest_token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay()])->save();

        $dead = new TripPlan(['title' => 'Kedaluwarsa', 'creation_mode' => CreationMode::Manual, 'days_count' => 1]);
        $dead->forceFill(['guest_token_hash' => hash('sha256', 'b'), 'expires_at' => now()->subMinute()])->save();

        $ids = TripPlan::query()->notExpired()->pluck('id')->all();

        $this->assertContains($live->id, $ids);
        $this->assertNotContains($dead->id, $ids);
    }
}

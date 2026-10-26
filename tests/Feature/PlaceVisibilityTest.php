<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\BuildsArchiveData;
use Tests\TestCase;

class PlaceVisibilityTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;

    public function test_published_scope_hides_draft_and_archived(): void
    {
        $published = $this->makePlace(PublishStatus::Published);
        $this->makePlace(PublishStatus::Draft);
        $this->makePlace(PublishStatus::Archived);

        $this->assertSame([$published->id], Place::query()->published()->pluck('id')->all());
    }

    public function test_guest_sees_only_published_places(): void
    {
        $this->assertTrue(Gate::forUser(null)->allows('view', $this->makePlace(PublishStatus::Published)));
        $this->assertFalse(Gate::forUser(null)->allows('view', $this->makePlace(PublishStatus::Draft)));
    }

    public function test_regular_user_cannot_edit_but_admin_can(): void
    {
        $place = $this->makePlace();

        $this->assertFalse(Gate::forUser($this->makeUser())->allows('update', $place));
        $this->assertTrue(Gate::forUser($this->makeUser(UserRole::Admin))->allows('update', $place));
    }

    public function test_only_super_admin_can_force_delete(): void
    {
        $place = $this->makePlace();

        $this->assertFalse(Gate::forUser($this->makeUser(UserRole::Admin))->allows('forceDelete', $place));
        $this->assertTrue(Gate::forUser($this->makeUser(UserRole::SuperAdmin))->allows('forceDelete', $place));
    }
}

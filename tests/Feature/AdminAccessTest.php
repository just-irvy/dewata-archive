<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_regular_user_gets_403(): void
    {
        $this->actingAs($this->makeUser(UserRole::User))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_and_super_admin_can_open_admin(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))->get('/admin')->assertOk();
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->get('/admin')->assertOk();
    }
}

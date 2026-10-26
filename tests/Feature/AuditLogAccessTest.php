<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_only_super_admin_can_read_audit_logs(): void
    {
        $this->get('/admin/audit-logs')->assertRedirect(route('login'));
        $this->actingAs($this->makeUser())->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($this->makeUser(UserRole::Admin))->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->get('/admin/audit-logs')->assertOk();
    }

    public function test_every_admin_listing_renders_for_an_admin(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        foreach (['places', 'hotels', 'regions', 'categories', 'tags', 'collections', 'media'] as $section) {
            $this->actingAs($admin)->get("/admin/{$section}")->assertOk();
        }
    }
}

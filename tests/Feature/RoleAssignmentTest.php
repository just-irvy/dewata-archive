<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\BuildsArchiveData;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'password-uji-123',
            'role' => 'super_admin',
        ]);

        $this->assertSame(UserRole::User, $user->fresh()->role);
    }

    public function test_registration_form_cannot_set_role(): void
    {
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'password-uji-123!',
            'password_confirmation' => 'password-uji-123!',
            'role' => 'super_admin',
        ]);

        $this->assertSame(
            UserRole::User,
            User::where('email', 'penyusup@example.test')->firstOrFail()->role,
        );
    }

    public function test_only_super_admin_passes_high_risk_gates(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $super = $this->makeUser(UserRole::SuperAdmin);

        $this->assertFalse(Gate::forUser($admin)->allows('view-audit-logs'));
        $this->assertFalse(Gate::forUser($admin)->allows('manage-roles'));
        $this->assertTrue(Gate::forUser($super)->allows('view-audit-logs'));
        $this->assertTrue(Gate::forUser($super)->allows('manage-roles'));
    }
}

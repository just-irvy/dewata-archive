<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('archive.admin.email');
        $password = config('archive.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ARCHIVE_ADMIN_EMAIL atau ARCHIVE_ADMIN_PASSWORD kosong. Super-admin tidak dibuat.');

            return;
        }

        if (app()->isProduction() && strlen($password) < 12) {
            $this->command?->error('Password super-admin di production minimal 12 karakter.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        // Akun yang sudah ada tidak ditimpa passwordnya. Seeder aman dijalankan ulang.
        if (! $user->exists) {
            $user->forceFill([
                'name' => config('archive.admin.name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        }

        $user->forceFill(['role' => UserRole::SuperAdmin])->save();
    }
}

# The Dewata Archive: Fase 0 (Foundation)

Paket ini adalah lapisan di atas instalasi Laravel 13 yang baru. Isinya skema database lengkap, enum, model, policy, middleware admin, tampilan auth, token desain, layout, seeder, CI, dan tes dasar.

Status verifikasi: seluruh file PHP lolos `php -l` (PHP 8.3), dan CSS terkompilasi dengan Tailwind 4.3. Blade, migrasi, dan tes belum dijalankan karena Composer tidak bisa mengakses Packagist dari lingkungan penyusun. Langkah 8 di bawah adalah verifikasi pertama Anda.

## Prasyarat

PHP 8.3 sampai 8.5 (disarankan 8.4), Composer 2, Node 22, dan MySQL 8 atau PostgreSQL. SQLite cukup untuk lokal dan CI.

## Langkah

1. Buat proyek tanpa starter kit.

   laravel new dewata-archive
   (pilih: none untuk starter kit, pilih Pest atau PHPUnit, database sesuai kebutuhan)

2. Pasang Fortify, lalu terbitkan action dan provider-nya.

   cd dewata-archive
   composer require laravel/fortify
   php artisan fortify:install

3. Pasang paket front-end.

   npm install alpinejs @fontsource-variable/playfair-display @fontsource-variable/inter @fontsource/courier-prime

4. Salin seluruh isi folder paket ini ke akar proyek. Empat file bawaan Laravel akan tertimpa: `routes/web.php`, `resources/css/app.css`, `resources/js/app.js`, dan `database/seeders/DatabaseSeeder.php`.

5. Lakukan empat penyesuaian manual.

   a. `app/Models/User.php`: tambahkan `use App\Concerns\HasArchiveRole;` di bagian import dan `use HasArchiveRole;` di dalam class. Jangan tambahkan `role` ke `$fillable`.

   b. `bootstrap/providers.php`: tambahkan `App\Providers\ArchiveServiceProvider::class` dan `App\Providers\AuthViewsServiceProvider::class` setelah `FortifyServiceProvider`.

   c. `config/fortify.php`: ubah `'home'` menjadi `'/account'`. Pada `features`, pertahankan `registration`, `resetPasswords`, `emailVerification`, `updateProfileInformation`, `updatePasswords`. Hapus `twoFactorAuthentication` (di luar cakupan MVP).

   d. Tambahkan isi `env.additions.txt` ke `.env` dan `.env.example`. Isi `ARCHIVE_ADMIN_EMAIL` dan `ARCHIVE_ADMIN_PASSWORD` hanya di `.env` lokal.

6. Pasang terjemahan Indonesia untuk pesan validasi dan auth. Periksa dulu kompatibilitas paket dengan Laravel 13.

   composer require laravel-lang/common --dev
   php artisan lang:add id

   Bila belum kompatibel, terbitkan `lang/id/validation.php`, `auth.php`, dan `passwords.php` secara manual.

7. Jalankan migrasi dan seeder, lalu build asset.

   php artisan migrate --seed
   npm run dev

8. Verifikasi Fase 0.

   php artisan test
   vendor/bin/pint --test

   Buka `/`, `/styleguide` (hanya local), `/register`, `/login`, `/account`, dan `/admin`. Login sebagai super-admin hasil seeder untuk melihat `/admin`.

## Kriteria selesai Fase 0 (FILE 18)

Instalasi baru dan baseline tes lulus. Tidak ada secret di repository. Token desain dan layout bersama terpasang. Skema, policy, dan middleware admin teruji. Hasil yang tidak sesuai dengan daftar ini harus diperbaiki sebelum masuk Fase 1.

Keputusan yang menyimpang dari blueprint ada di `docs/KEPUTUSAN.md`. Rencana desain ada di `docs/DESAIN.md`.

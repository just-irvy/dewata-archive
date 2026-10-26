# The Dewata Archive: Fase 0 dan Fase 1

Paket ini kumulatif dan berlaku di atas instalasi Laravel 13 yang baru. Fase 0 memuat skema, enum, model, policy, auth, token desain, dan layout. Fase 1 menambah ruang kerja admin, alur draf ke terbit dengan pemeriksaan kesiapan, audit log, unggah media, dan halaman tempat publik.

Status verifikasi: semua file PHP lolos `php -l` (PHP 8.3), CSS terkompilasi dengan Tailwind 4.3, dan keseimbangan direktif serta tag komponen Blade sudah dicek dengan skrip. Migrasi, Blade yang dirender, dan seluruh tes belum dijalankan karena Composer tidak bisa mengakses Packagist dari lingkungan penyusun. Langkah 8 adalah verifikasi pertama Anda.

## Prasyarat

PHP 8.3 sampai 8.5 (disarankan 8.4) dengan ekstensi gd, Composer 2, Node 22, dan MySQL 8 atau PostgreSQL. SQLite cukup untuk lokal dan CI.

## Langkah

1. Buat proyek tanpa starter kit.

   laravel new dewata-archive

2. Pasang Fortify dan terbitkan action-nya.

   cd dewata-archive
   composer require laravel/fortify
   php artisan fortify:install

3. Pasang paket front-end.

   npm install alpinejs @fontsource-variable/playfair-display @fontsource-variable/inter @fontsource/courier-prime

4. Salin seluruh isi folder paket ini ke akar proyek. File bawaan Laravel yang tertimpa: `routes/web.php`, `resources/css/app.css`, `resources/js/app.js`, `database/seeders/DatabaseSeeder.php`.

5. Penyesuaian manual.

   a. `app/Models/User.php`: tambahkan `use App\Concerns\HasArchiveRole;` di import dan `use HasArchiveRole;` di dalam class. Jangan masukkan `role` ke `$fillable`.

   b. `bootstrap/providers.php`: tambahkan `App\Providers\ArchiveServiceProvider::class` dan `App\Providers\AuthViewsServiceProvider::class` setelah `FortifyServiceProvider`.

   c. `config/fortify.php`: ubah `'home'` menjadi `'/account'`. Pada `features`, pertahankan `registration`, `resetPasswords`, `emailVerification`, `updateProfileInformation`, `updatePasswords`. Hapus `twoFactorAuthentication`.

   d. Tambahkan isi `env.additions.txt` ke `.env` dan `.env.example`.

6. Terjemahan Indonesia untuk pesan validasi. Periksa dulu kompatibilitas dengan Laravel 13.

   composer require laravel-lang/common --dev
   php artisan lang:add id

7. Migrasi, seeder, tautan penyimpanan, dan asset.

   php artisan migrate --seed
   php artisan storage:link
   npm run dev

8. Verifikasi.

   vendor/bin/pint
   php artisan test

   Jalankan `vendor/bin/pint` sekali untuk merapikan gaya kode sebelum CI memakai `pint --test`. Tes tidak membutuhkan `npm run build`.

## Uji manual Fase 1

Login sebagai super-admin hasil seeder lalu jalankan urutan ini. Buat satu kategori dan tandai terbit. Buat tempat baru, isi sumber, tanggal verifikasi, dan status "Terverifikasi", lalu simpan. Unggah satu foto dengan alt text dan kredit. Pada halaman tempat, panel Kesiapan terbit harus menyatakan semua syarat terpenuhi. Klik Pratinjau dan ganti lebar Desktop dan Ponsel. Klik Terbitkan lalu buka `/places/{slug}` dari jendela privat. Arsipkan tempat itu dan pastikan URL publiknya menjadi 404. Terakhir buka Audit log dan pastikan setiap langkah tercatat dengan pelakunya.

## Kemungkinan titik gagal pertama

Rute `login`, `register`, dan `logout` berasal dari Fortify. Bila tes halaman publik gagal dengan "route not defined", langkah 2 atau 5c belum tuntas. Tes unggah foto membutuhkan ekstensi gd. Bila `fortify:install` menghasilkan struktur berbeda dari yang diasumsikan, periksa `app/Providers/FortifyServiceProvider.php` dan pastikan tidak ada yang mendaftarkan tampilan auth lain.

## Kriteria selesai Fase 1 (FILE 18)

Admin dapat mengelola data lewat UI dan publik hanya melihat data terbit. Keduanya tercakup oleh tes `PlaceAdminTest`, `PublishWorkflowTest`, dan `PublicPlaceTest`. Keputusan yang menyimpang dari blueprint ada di `docs/KEPUTUSAN.md`.

# The Dewata Archive: panduan setup

Bagian awal di bawah adalah catatan instalasi Fase 0 dan 1 (paket ditimpa ke instalasi Laravel baru). Repository ini sekarang sudah utuh, jadi untuk clone baru cukup ikuti README. Bagian Fase 4 dan 5 serta Produksi ada di akhir dokumen.

## Fase 0 dan Fase 1 (catatan historis)

Paket ini kumulatif dan berlaku di atas instalasi Laravel 13 yang baru. Fase 0 memuat skema, enum, model, policy, auth, token desain, dan layout. Fase 1 menambah ruang kerja admin, alur draf ke terbit dengan pemeriksaan kesiapan, audit log, unggah media, dan halaman tempat publik.

Status verifikasi saat Fase 4 dan 5: `composer install` dari composer.lock, migrasi, 334 tes PHP, 39 tes JavaScript, `npm run build`, dan alur planner di Chromium headless (desktop 1280 px dan ponsel 360 sampai 390 px) sudah dijalankan dan lulus.

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


## Fase 4: planner

Tidak ada langkah instalasi tambahan. Jalankan `php artisan migrate` untuk kolom `trip_plans.generation_notes`. Planner aktif bawaan (`ARCHIVE_PLANNER_ENABLED=true`).

Aturan rekomendasi ada di `config/planner.php`: batas hari, tema, tempat per hari, masa simpan draf tamu, kepadatan tiap tempo, bobot skor, dan radius pengelompokan. Ubah angka di sana, bukan di kode, lalu naikkan `recommendation_version`.

### Uji manual Fase 4

1. Tanpa login buka `/planner`. Dua pilihan tampil setara.
2. Dapatkan rekomendasi: isi langkah 1, lanjut ke langkah 2 tanpa memilih tema dan tekan Lanjut. Harus muncul pesan. Pilih dua tema, lanjut sampai Tinjau, tekan Ubah pada Wilayah, kembali ke Tinjau. Jawaban lain tetap ada.
3. Susun draf. Editor menampilkan alasan per tempat, catatan penyusunan, dan tombol Simpan ke akun.
4. Naikkan, turunkan, pindahkan ke hari lain, hapus, dan ganti dengan cadangan. Ubah catatan: tersimpan tanpa memuat ulang halaman.
5. Susun sendiri: buat arsip 2 hari, cari "pura", tambahkan tiga tempat, seret satu ke Hari 2.
6. Tekan Simpan ke akun, lalu daftar akun baru. Setelah daftar, arsip langsung terbuka di `/account/plans/{id}` dan sudah milik akun.
7. Dengan peta aktif (lihat docs/PETA.md), pilih Hari 1 dan Hari 2 di panel peta. Marker berlabel D1·1, D1·2, dan H untuk penginapan. Atur penginapan di "Atur hari ini" dan pastikan rute dimulai dari H.
8. Buka URL arsip itu dari jendela privat atau akun lain: harus 404.

## Fase 5: produksi

Checklist ini melengkapi FILE 20 blueprint.

1. `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-anda`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`. Database dengan user berprivilege minimum.
2. `TRUSTED_PROXIES`: kosongkan bila PHP langsung menerima koneksi. Isi IP load balancer bila di belakang proxy. Tanpa ini, rate limit membaca IP proxy (semua pengguna satu kuota) atau bisa dipalsukan.
3. Cron untuk scheduler (pembersihan draf tamu kedaluwarsa setiap hari 03.15 WITA dan token reset kata sandi):

   * * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1

4. Queue worker bila memakai `QUEUE_CONNECTION=database`: `php artisan queue:work --tries=3` lewat supervisor atau layanan setara.
5. Optimasi saat deploy: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
6. Header keamanan dikirim otomatis (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, dan HSTS lewat HTTPS). Halaman akun dan draf dikirim dengan `Cache-Control: private, no-store`.
7. Batas laju: pembuatan rencana 10 per menit, perubahan rencana 120 per menit, pencarian publik 90 per menit, login 5 per menit (per user bila login, per IP bila tamu). Ubah di `ArchiveServiceProvider`.
8. Backup database dan `storage/app/public` terenkripsi, lalu uji restore sebelum rilis.
9. Google Maps: key browser dibatasi referrer dan API, key server dibatasi IP, budget alert aktif (docs/PETA.md).
10. Setelah deploy, uji dari browser bersih di ponsel dan desktop: daftar, planner kedua mode, simpan, peta, dan halaman admin.

## Foto sampul beranda

Masuk sebagai admin, buka Admin, Beranda. Unggah foto Pulau Bali (lebar minimal 1000 piksel; tampilan memotong ke 4:5 di desktop), isi alt text dan kredit atau lisensi. Pastikan `php artisan storage:link` sudah dijalankan dan `APP_URL` sama dengan alamat situs, karena URL foto dibentuk dari keduanya. Sumber foto yang aman: foto milik sendiri, Wikimedia Commons dengan lisensi CC (cantumkan nama pembuat dan lisensinya), atau Unsplash (cantumkan nama fotografer).

## Hapus semua tempat dan hotel

Untuk mengganti katalog dari nol. Data dihapus permanen, termasuk yang terbit, beserta fotonya. Rencana pengguna tetap ada tetapi tempat di dalamnya hilang. Buat cadangan database dulu.

Lewat admin (super-admin): Admin, Ringkasan, bagian Zona berbahaya, Hapus semua tempat dan hotel. Ketik HAPUS SEMUA dan kata sandi Anda.

Lewat terminal:

    php artisan archive:reset-catalog --dry-run                    # lihat jumlahnya saja
    php artisan archive:reset-catalog --actor=email-super-admin     # hapus, dengan konfirmasi

## Katalog awal hasil riset

`katalog-dewata-2026-11-01.csv` berisi 26 tempat di 9 wilayah dan 1 hotel, dengan koordinat dan sumber. Impor lewat Admin, Impor dataset. Semua baris berstatus "Perlu ditinjau" dengan tanggal riset, sehingga langsung lolos syarat terbit: setelah impor, buka Admin, Tempat, lalu "Terbitkan semua yang siap".

`perlu-dilengkapi.csv` berisi entri yang belum punya koordinat tepercaya. Isi kolom latitude dan longitude (misalnya dari Google Maps: klik kanan titik, salin angka), lalu impor seperti biasa. Baris tambahan Anda bisa memakai format yang sama; id harus unik.

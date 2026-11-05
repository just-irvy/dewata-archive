# The Dewata Archive

Panduan Bali yang dikurasi, disusun sebagai arsip pribadi: tempat, wilayah, penginapan, dan perencana perjalanan dengan sumber data yang jelas. Dibangun dengan Laravel 13, Blade, Tailwind 4, Alpine.js, dan Google Maps Platform, mengikuti *The Dewata Archive Product Blueprint v1.2*.

## Status

| Fase | Isi | Status |
|---|---|---|
| 0 Foundation | Skema, auth (Fortify), token desain, layout | Selesai |
| 1 Content core | Admin CRUD, draf ke terbit, audit log, media, impor dataset bertahap | Selesai |
| 2 Discovery UI | Beranda, jelajah, filter, detail, wilayah, koleksi, hotel, bookmark | Selesai |
| 3 Maps | Peta, marker, rute jalan, fallback jujur | Selesai |
| 4 Planner | Mode Rekomendasi (wizard 6 langkah) dan Manual, editor bersama, draf tamu, simpan ke akun | Selesai |
| 5 Hardening | Header keamanan, rate limit, retensi draf tamu, lokal dan zona waktu, CI | Selesai |
| 6 Chatbot Gemini | Adapter backend, tool calling, sumber jawaban | Kode tersedia, dimatikan secara bawaan. Lihat docs/CHATBOT.md |

## Menjalankan secara lokal

Prasyarat: PHP 8.3 sampai 8.5 (disarankan 8.4) dengan ekstensi gd dan exif, Composer 2, Node 22.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
# isi ARCHIVE_ADMIN_EMAIL dan ARCHIVE_ADMIN_PASSWORD di .env untuk akun super-admin awal
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev        # atau npm run build
php artisan serve
```

Katalog awal dimasukkan lewat Admin, Impor dataset (`combined_dataset_150_v4.csv`). Impor hanya membuat draf. Tempat baru tampil ke publik dan ke planner setelah editor mengisi sumber dan tanggal verifikasi lalu menerbitkannya.

## Menguji

```bash
vendor/bin/pint --test   # gaya kode
php artisan test         # 348 tes PHP
npm run test:js          # 39 tes JavaScript (peta dan planner)
```

## Dokumen

- `PANDUAN-SETUP.md`: instalasi, produksi, cron, dan uji manual per fase.
- `docs/KEPUTUSAN.md`: keputusan yang menyimpang dari atau melengkapi blueprint, per fase.
- `docs/PETA.md`: menyalakan dan mengamankan Google Maps.
- `docs/DESAIN.md`, `docs/FOTO.md`, `docs/AUDIT-DATASET.md`, `docs/KURASI-DATASET-V2.md`.

## Prinsip yang ditegakkan kode

Planner tidak pernah meminta atau menghitung anggaran perjalanan; satu-satunya angka uang adalah kisaran harga hotel per malam. Hanya tempat terbit dengan koordinat valid yang bisa masuk itinerary. Rencana pengguna hanya bisa dibuka pemiliknya (admin pun tidak). Jarak garis lurus tidak pernah ditampilkan sebagai jarak jalan. Semua fitur utama berjalan tanpa Gemini.

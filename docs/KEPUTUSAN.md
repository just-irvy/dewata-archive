# Keputusan dan celah blueprint

Dicatat saat Fase 0, 26 Oktober 2026. Tiap butir menyebut apa yang ditemukan dan apa yang dilakukan.

## Teknis

Laravel 13 mensyaratkan PHP 8.3 dan mendukung 8.3 sampai 8.5 menurut dokumentasi rilis. Satu README starter kit pihak ketiga menyebut PHP 8.4.1 karena komponen Symfony 8. Klaim itu tidak terkonfirmasi di dokumentasi resmi, tetapi PHP 8.4 aman untuk keduanya, jadi CI dan hosting memakai 8.4.

Autentikasi memakai Laravel Fortify dengan tampilan Blade buatan sendiri. Alasannya: Breeze (satu-satunya kit resmi berbasis Blade dan Alpine) sudah tidak menerima pembaruan, dan kit resmi baru hanya menyediakan React, Vue, Svelte, dan Livewire. Fortify tetap dipelihara, dan tampilannya memang harus dibuat ulang agar sesuai desain arsip. Blueprint melarang auth buatan sendiri, dan Fortify memenuhi larangan itu karena logika auth tetap dari paket resmi. Dua faktor dikeluarkan dari MVP karena tidak ada di blueprint.

Zona waktu aplikasi Asia/Makassar (WITA). Tanggal di masthead dan label verifikasi mengikuti hari di Bali. Timestamp database ikut tersimpan dalam WITA, jadi jangan campur dengan layanan yang mengasumsikan UTC.

Laravel 13 membawa Laravel AI SDK resmi. Blueprint meminta adapter backend sendiri untuk Gemini. Putuskan di Fase 6, setelah membandingkan dukungan function calling Gemini pada SDK itu.

## Celah skema (FILE 13 dan 14)

trip_plans tidak punya masa kedaluwarsa guest. Ditambah kolom expires_at.

Rute harian memakai hotel sebagai origin, tetapi tidak ada tempat menyimpan hotel pilihan. Ditambah hotel_id dan return_to_hotel pada trip_plan_days. Hotel bisa berbeda tiap hari.

Hanya place_media yang didefinisikan, padahal kartu hotel butuh foto dengan hak pakai. Diganti satu tabel media polimorfik untuk tempat dan hotel.

Tabel places tidak memuat kolom yang diminta FILE 16: external_id, opening_hours, accessibility, status verifikasi tiap data, source_updated_at, attribution, price_info. Semua ditambahkan. typical_visit_minutes ditambah karena timeline membutuhkan estimasi durasi.

price_basis dipakai dengan dua arti. FILE 8 dan 13 menyebut jenis sumber (terkurasi, penyedia, estimasi), FILE 16 menyebut satuan (per malam, per kamar). Dipisah: price_basis berisi jenis sumber, dan satuan dikunci sebagai per kamar per malam. Hotel juga mendapat price_status.

item_type pada trip_plan_items tidak didefinisikan dan place_id sudah menentukan jenis item. Kolom dihapus. Tambahkan lagi bila ada jenis item kedua.

Role: blueprint menyebut admin dengan izin khusus untuk audit log. Diterapkan tiga nilai role (user, admin, super_admin), dan audit log serta manajemen role hanya untuk super_admin. Cara ini menghindari sistem permission yang kompleks, sesuai saran blueprint.

place_id pada trip_plan_items memakai restrictOnDelete. Tempat yang sudah dipakai di rencana diarsipkan, bukan dihapus permanen.

## Sitemap

FILE 3 punya /regions/{slug} tetapi tidak punya halaman indeks, padahal navigasi memuat Wilayah. Ditambah /regions.

## Bahasa

UI berbahasa Indonesia (keputusan terbuka pada FILE 21), nama merek tetap The Dewata Archive. Semua teks lewat `lang/id/ui.php` agar mudah dilokalkan.

## Hal yang harus dicek ulang di fase berikutnya

Fase 3: klaim batas 25 intermediate waypoint dan tarif lebih tinggi pada 11 atau lebih. Cek dokumentasi Routes Library saat implementasi, jangan ambil dari blueprint. Cek juga teks atribusi Google yang berlaku.

Fase 4: masa simpan draft guest belum ditetapkan di blueprint. Tentukan angkanya sebelum fase ini dan ubah halaman Tentang bila perlu.

Fase 6: cek kebijakan kunci Gemini (R4 menyebut kebijakan berubah pada 2026).

## Lisensi font

Playfair Display, Inter, dan Courier Prime berlisensi SIL OFL 1.1 dan di-host sendiri lewat paket Fontsource. Hanya bobot yang dipakai yang dimuat: Playfair variabel (termasuk italik), Inter variabel, dan Courier Prime 400.

# Fase 1: konten inti

## Alur terbit

Status hanya berubah lewat satu action, ChangeStatus. Menerbitkan menjalankan pemeriksaan kesiapan. Blocker menghentikan publikasi dan warning hanya ditampilkan.

Blocker tempat: ringkasan kosong, wilayah belum terbit, tidak ada kategori terbit, tautan sumber kosong, tanggal verifikasi kosong, status verifikasi "Belum diketahui", koordinat tidak valid, dan foto tanpa alt text atau tanpa kredit atau lisensi. Warning tempat: status "Perlu ditinjau", belum ada foto, koordinat di luar kotak Bali, serta jam buka atau aksesibilitas terisi tetapi statusnya belum diketahui.

Blocker hotel mengikuti tempat, ditambah harga terisi tanpa jenis sumber atau tanpa status verifikasi harga. Hotel tanpa harga boleh terbit dan tampil sebagai "harga tidak tersedia". Blocker koleksi: kosong atau berisi tempat yang belum terbit.

Wilayah tidak bisa di-unpublish selama masih dipakai tempat atau hotel terbit. Kategori tidak bisa di-unpublish bila ada tempat terbit yang akan kehilangan kategori terbit terakhirnya.

## Data yang tidak ditampilkan

Jam buka dan aksesibilitas hanya tampil di halaman publik bila statusnya "Terverifikasi". Selain itu halaman menampilkan "Cek jam buka sebelum berkunjung" atau "Belum ada data aksesibilitas yang terverifikasi". Foto tanpa alt text atau hak pakai tidak tampil di halaman publik, hanya di pratinjau admin.

## URL dan penghapusan

Slug terkunci setelah terbit agar URL stabil. Draf dan arsip selalu 404 di URL publik, juga untuk admin. Admin memakai pratinjau. Tempat, hotel, dan koleksi memakai soft delete. Yang terbit tidak bisa dihapus, dan tempat yang sudah dipakai di rencana pengguna tidak bisa dihapus sama sekali. Tidak ada tombol hapus permanen di UI.

## Audit

Dicatat: pembuatan dan perubahan record (hanya kolom yang berubah), perubahan pivot kategori dan tag, perubahan harga hotel, perubahan status, penambahan dan pelepasan tempat di koleksi, serta unggah, ubah, dan hapus media. IP disimpan sebagai HMAC berkunci aplikasi. Belum dicatat: login dan logout, karena belum ada UI manajemen role.

## Halaman publik lebih awal

Blueprint menaruh halaman detail di Fase 2. Rute /places/{slug} dibuat sekarang karena aturan "publik hanya melihat data terbit" tidak bisa diuji tanpa halaman publik. Halaman ini minimal dan akan diganti di Fase 2 dengan peta, tempat terkait, dan bookmark.

## Konten teks

Uraian tempat berupa teks biasa, paragraf dipisah baris kosong, dirender dengan escape. Tidak ada editor rich text, jadi tidak ada risiko XSS dari konten dan tidak perlu sanitizer.

## Angka yang perlu Anda putuskan

Ambang daftar tinjauan di config/archive.php: tempat 365 hari dan harga hotel 90 hari. Keduanya angka awal, bukan dari blueprint. Kotak batas Bali juga ada di config dan hanya memicu peringatan.

## Belum dikerjakan

Gambar belum diubah ukurannya atau dibuat thumbnail, jadi foto besar dikirim apa adanya. Itu masuk Fase 5 (performa). Penyimpanan media memakai disk public lokal, pindahkan ke object storage sebelum produksi bila hosting tidak punya disk persisten. Impor dataset bertahap (FILE 16) belum dibangun karena dataset belum diterima. Halaman publik hotel, wilayah, dan koleksi menyusul di Fase 2.

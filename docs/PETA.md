# Peta dan rute

Panduan menyalakan, mengamankan, dan menguji peta. Bagian "Yang harus diverifikasi" jujur soal hal yang belum bisa dicek dari lingkungan penyusun.

## Cara kerjanya

Halaman tanpa peta tidak menghubungi Google sama sekali. Pada halaman dengan peta (detail tempat, detail hotel, wilayah, koleksi), library Google baru diunduh saat peta mendekati layar. Tiap muat peta adalah satu transaksi tertagih, jadi pemuatan malas ini sekaligus pengendali biaya. Bila peta gagal, halaman tetap utuh dan pengguna melihat pesan serta tautan Google Maps.

Rute dihitung di browser lewat Routes Library (`Route.computeRoutes`, polyline dari `createPolylines()`). Server punya adapter terpisah (`App\Services\Maps`) untuk kebutuhan chatbot dan snapshot jarak per leg, memakai Routes API dengan server key.

## Langkah menyalakan

1. Di Google Cloud, buat dua proyek atau dua key terpisah untuk development dan production.
2. Aktifkan Maps JavaScript API dan Routes API.
3. Buat key browser. Batasi dengan HTTP referrer ke domain Anda (dan `localhost` untuk development), dan batasi API-nya ke Maps JavaScript API dan Routes API saja.
4. Buat key server untuk panggilan dari backend. Batasi dengan alamat IP server dan hanya ke Routes API.
5. Buat Map ID jenis JavaScript, vector. Untuk development, `DEMO_MAP_ID` cukup.
6. Atur budget alert dan kuota harian di Google Cloud sebelum produksi. Batas `ARCHIVE_ROUTES_DAILY_CAP` hanya pengaman tambahan untuk panggilan server, bukan pengganti.
7. Isi `GOOGLE_MAPS_BROWSER_KEY`, `GOOGLE_MAPS_MAP_ID`, dan `GOOGLE_MAPS_SERVER_KEY` di `.env`. Key tidak pernah dimasukkan ke repository.
8. `npm install @googlemaps/js-api-loader`, lalu `npm run dev`.

## Gaya ink dan sepia lewat Map ID

Marker lanjutan mensyaratkan Map ID, dan gaya peta dipasang lewat cloud-based styling pada Map ID itu. Opsi `styles` pada `MapOptions` tidak dipakai bersama Map ID, sehingga blueprint FILE 11 (pilih salah satu pendekatan) diterapkan di sisi cloud. Buat gaya di Google Cloud Console, lalu kaitkan ke Map ID. Nilai warna:

Geometri dasar charcoal #25231F. Jalan taupe #62584A. Air teal gelap #193438. Label ivory #D8CBB5. Kurangi label POI yang tidak relevan agar marker menjadi fokus. Garis rute berwarna rust #9C4935 digambar di kode sebagai polyline, bukan lewat gaya peta.

Periksa kontras label terhadap latar sebelum dipublikasikan, terutama pada layar kecil.

## Moda perjalanan

Mobil dan driver memakai DRIVING. Motor memakai TWO_WHEELER. Transport umum sengaja tidak dirutekan karena blueprint melarang menjanjikan transport publik yang tidak diverifikasi.

Google menandai rute jalan kaki, sepeda, dan motor sebagai beta dan mewajibkan peringatan di UI. Peringatan itu sudah ditambahkan otomatis. Permintaan TWO_WHEELER juga ditagih lebih tinggi.

## Biaya

Maksimum Google adalah 25 intermediate waypoint, dan 11 ke atas ditagih lebih tinggi (dokumentasi diperbarui 17 September 2026). Batas planner ditetapkan 10 di `RouteQuery::MAX_INTERMEDIATES` dan `MAX_INTERMEDIATES` pada helper JavaScript. Satu rute per hari dengan seluruh pemberhentian sebagai waypoint, bukan satu permintaan per pasang titik. Rute dihitung ulang hanya saat urutan, hotel, moda, atau opsi kembali ke hotel berubah.

Panggilan server memakai routingPreference TRAFFIC_UNAWARE agar tarifnya lebih rendah. Konsekuensinya estimasi waktu tidak memperhitungkan lalu lintas, dan UI sudah menyebutnya estimasi.

## Penyimpanan dan atribusi

Respons Google tidak di-cache. Aplikasi hanya menyimpan angka jarak dan durasi per leg beserta waktu hitungnya (kolom route_* pada trip_plan_items), sesuai blueprint. Atribusi Google ditampilkan oleh peta itu sendiri. Periksa persyaratan atribusi yang berlaku sebelum produksi, terutama bila jarak dan waktu rute ditampilkan di luar peta.

## Menguji

Di environment local buka `/dev/maps`. Halaman itu merutekan enam tempat terbit pertama (dan hotel pertama sebagai titik awal) dengan moda yang bisa diganti. Uji juga tiga kegagalan: kosongkan key (peta tidak muncul, halaman utuh), isi key salah (pesan "Peta tidak dapat dimuat"), dan putuskan jaringan saat rute dihitung (marker tampil, pesan "Rute jalan belum tersedia").

`npm run test:js` menjalankan 29 tes untuk helper dan controller. Tes PHP untuk adapter server memakai Http::fake dan tidak menghubungi Google.

## Yang harus diverifikasi dengan key sungguhan

Tes JavaScript memakai objek Google tiruan, jadi bentuk request ke API Google belum teruji terhadap Google sebenarnya. Dokumentasi mengonfirmasi `Route.computeRoutes`, field mask wajib, `legs` harus diminta agar intermediate disertakan, dan origin berupa objek lat dan lng. Yang masih perlu dilihat langsung di browser: bentuk `intermediates` sebagai `{location: {lat, lng}}`, apakah `routingPreference` diterima untuk TWO_WHEELER pada Routes API, dan apakah ketiadaan opsi `styles` bersama Map ID berperilaku seperti yang dijelaskan di atas. Bila ada yang berbeda, perbaikannya terpusat di `resources/js/maps/helpers.mjs` dan `app/Services/Maps/GoogleRoutesProvider.php`.

Satu catatan keamanan: bila nanti ada Content-Security-Policy, domain Google Maps perlu diizinkan untuk script, gambar, dan koneksi.

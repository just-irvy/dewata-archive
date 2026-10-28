# Kurasi dataset v2 (150 baris dari tiga sumber)

Pembaruan v3 (27 Oktober 2026): berkas yang diimpor sekarang combined_dataset_150_v4.csv. Isinya sama dengan v2 ditambah tiga tempat yang sudah diriset penuh: Museum Yadnya (Badung), Desa Adat Penglipuran (Bangli), dan Subak Jatiluwih (Tabanan). Ketiganya punya ringkasan parafrase, tautan sumber, tanggal verifikasi 2026-10-27, dan status "Perlu ditinjau", sehingga lolos pemeriksaan kesiapan dan bisa diterbitkan dengan satu klik. Status "Perlu ditinjau" dipilih dengan sengaja karena pemeriksaan dilakukan terhadap sumber web, belum oleh editor manusia. Sisa 147 baris tetap draf tanpa ringkasan. Importer sekarang membaca kolom verified_at dan verification_status.

Dibuat pada 27 Oktober 2026 dari tiga berkas: bali_destinations_20260721_121044.csv (A), dataset_tempat_wisata_bali_1_.csv (B), dan data_traveloka.csv (C). Hasilnya combined_dataset_150_v2.csv. Seluruh pemilihan dilakukan oleh `tools/curate_dataset.py` dengan aturan eksplisit, jadi bisa diulang dan diubah.

## Temuan penting lebih dulu

Berkas lama combined_dataset_150_rows hanya 33 dari 150 barisnya yang bisa ditelusuri ke ketiga sumber ini (26 persis, 7 mirip). Sisanya 117 baris tidak ada di sumber mana pun, koordinatnya hanya 4 desimal, dan teksnya bernada promosi. Berkas itu sebaiknya diganti sepenuhnya oleh v2.

Dugaan saya, dan ini inferensi dari pola URL dan struktur kolom, ketiga sumber adalah hasil scraping: B dan A dari Google Maps, C dari Traveloka. Syarat layanan keduanya bisa membatasi pemakaian ulang. Gunakan data ini sebagai daftar penemuan internal, tulis teks sendiri, dan verifikasi tiap tempat dari sumber resmi sebelum terbit. Harga Traveloka jangan ditampilkan ke publik tanpa izin, sesuai prinsip blueprint bahwa harga dari penyedia membutuhkan kontrak data.

## Kualitas per sumber

Sumber A (1303 baris). Kolom category adalah kata kunci pencarian, bukan jenis tempat. Sebanyak 69 persen nama pada kategori "Alam" memuat kata "alam" (contoh: "Toko Alam Baru", "Alam Bali Hotel"), dan kategori "Budaya" memuat "Pantai Pekutatan", "Secret Penida Restaurant & Bar", dan kantor dinas. Ada 192 nama ganda karena tempat yang sama muncul di banyak pencarian area (Abuela Coffee lima kali). Label area adalah kueri, bukan lokasi: pada 130 dari 1090 baris unik, kabupaten yang ditentukan dari koordinat berbeda dengan kabupaten label. Kolom ig_url dan tiktok_url kosong seluruhnya, visual_assets_indicator bernilai TRUE di semua baris, dan contact_wa rusak menjadi notasi ilmiah oleh Excel (6.28181E+12) sehingga tidak bisa dipakai. Harga kuliner dan kopi bernilai tengah 25 dengan satuan tidak jelas. Koordinatnya sendiri baik: 7 desimal, kecuali 7 titik di luar Bali.

Sumber B (761 baris). Sumber terbaik untuk wisata. Punya kabupaten yang benar, rating, dan tautan Google Maps untuk seluruh baris, dan tautan itu memuat koordinat pin tempat. Sebanyak 31 baris, semuanya Denpasar, punya koordinat rusak berformat "-8,671,722". Pemulihan dari teks hanya cocok dengan tautan pada 9 dari 31 baris, jadi koordinat selalu diambil dari tautan. Kolom kategori adalah label Google dan tidak selalu konsisten (Taman Anyar - Traditional Balinese House berlabel Alam). Ada 44 baris berating tepat 5,0, yang biasanya berarti sedikit ulasan, dan sumber tidak memuat jumlah ulasan.

Sumber C (1012 baris). Hanya hotel, dengan harga normal dan harga setelah diskon (diskon tengah 25 persen, 175 baris diskon 50 persen atau lebih), rating skala 10, tanpa koordinat dan tanpa tanggal. Hanya 20 dari 1012 hotel yang bisa dicocokkan dengan koordinat di sumber A lewat nama persis. Satu dibatalkan karena label lokasi Traveloka bertentangan dengan koordinat A ("Hotel Puri Bali", nama yang dipakai banyak properti), sehingga 19 hotel berharga Traveloka lolos. Sisa 992 hotel tidak bisa dipakai karena koordinat tidak boleh dikarang.

## Aturan pemilihan

Wisata (70, semuanya dari B). Koordinat dari tautan Google. Buang duplikat nama atau koordinat (22), nama non-wisata (1), dan rating di bawah 4,3, kosong, atau tepat 5,0 (134), menyisakan 604. Kuota per kabupaten: Badung 8, Bangli 7, Buleleng 10, Denpasar 5, Gianyar 9, Jembrana 6, Karangasem 9, Klungkung 7, Tabanan 9. Di dalam kabupaten, jenis wisata dipilih bergilir (Alam, Budaya, Rekreasi, Umum) dan diurutkan menurut rating.

Kuliner (20) dan kafe (20) dari A. Hanya baris yang kabupatennya dari koordinat sama dengan kabupaten label area, dengan titik referensi terdekat dalam 15 km. Nama harus memuat kata makanan atau kopi. Kuota per kabupaten dibagi merata menurut ukuran. Sumber A tidak punya sinyal kualitas, jadi urutan di dalam kuota memakai kelengkapan data lalu hash tetap. Itu berarti pilihan ini cukup arbitrer dan sebaiknya ditinjau editor.

Hotel (40). Prioritas pertama 18 hotel yang harganya dari Traveloka dan koordinatnya dari A. Sisanya 22 dari A saja (nama memuat kata penginapan, harga di atas 100 ribu), dengan kuota per kabupaten. Harga Traveloka dicatat sebagai harga setelah diskon, harga normal disimpan di kolom note. Jenis akomodasi diturunkan dari kata pada nama (villa, resort, guesthouse, selain itu hotel).

Kabupaten pada A dan C ditentukan dari koordinat dengan tiga tetangga terdekat di titik B yang kabupatennya diketahui. Akurasinya diuji leave-one-out pada B sendiri: 92 persen. Baris A dengan kabupaten koordinat yang berbeda dari label area dibuang, jadi baris yang lolos punya dua petunjuk yang sepakat.

Tema diturunkan dari kata pada nama dan kategori sumber (Pantai, Pura dan Arsitektur, Warisan Budaya, Alam, Rekreasi, Kuliner, Kopi). Ini aturan kata kunci, bukan penilaian editorial.

## Hasil

150 baris: Hotel 40, Wisata 70 (Alam 22, Budaya 18, Rekreasi 15, Umum 15), Kuliner 20, Kafe 20. Sebaran menurut kabupaten: Gianyar 23, Badung 22, Buleleng 20, Tabanan 17, Karangasem 16, Klungkung 15, Denpasar 14, Bangli 12, Jembrana 11. Semua sembilan kabupaten terwakili, dan Badung dengan Gianyar tinggal 30 persen dari seluruh baris (berkas lama 66 persen). Semua koordinat 7 desimal, tidak ada nama atau koordinat ganda, dan tidak ada dua titik yang berjarak kurang dari 50 meter. Sebanyak 70 baris (seluruh wisata) punya tautan sumber.

Uji impor pada berkas ini: 0 baris error dan 150 slug unik. Satu-satunya peringatan adalah 150 baris tanpa ringkasan.

## Yang sengaja dibiarkan kosong

Ringkasan kosong di semua baris. Tidak ada sumber yang memuat deskripsi, dan blueprint melarang mengarang. Karena ringkasan kosong menjadi penghalang terbit, tiap tempat tidak akan terbit sebelum editor menulisnya. Tanggal verifikasi juga kosong di semua baris. Alamat dan lisensi tidak ada di sumber mana pun.

## Keputusan yang perlu Anda ambil

1. Apakah kuota per kabupaten sudah sesuai kebutuhan planner. Ubah QUOTAS di skrip lalu jalankan ulang.
2. Apakah 22 hotel dari A (harga satuan tidak jelas) layak dipertahankan, atau cukup 18 hotel Traveloka.
3. Siapa yang menulis ringkasan, memverifikasi, dan memotret atau melisensikan foto untuk tiap tempat.
4. Bagaimana status hukum penggunaan data hasil scraping ini, terutama sebelum situs dibuka untuk publik.

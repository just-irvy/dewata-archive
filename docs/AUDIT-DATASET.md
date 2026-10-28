# Audit dataset combined_dataset_150_rows (berkas lama)

Catatan 27 Oktober 2026: berkas ini sudah digantikan oleh combined_dataset_150_v2.csv yang disusun dari tiga sumber mentah. Lihat docs/KURASI-DATASET-V2.md. Audit di bawah dipertahankan sebagai catatan, dan temuannya (koordinat 4 desimal, teks promosi, hanya 33 dari 150 baris yang bisa ditelusuri ke sumber) menjadi alasan penggantian.

Diperiksa pada 26 Oktober 2026 terhadap blueprint FILE 13, 16, dan 19. Angka di bawah berasal dari menjalankan pemeta impor pada seluruh 150 baris, bukan perkiraan.

## Ringkasan

Dataset layak dipakai sebagai kerangka awal, tetapi belum layak terbit. Strukturnya bersih: 150 baris, 9 kolom, tidak ada sel kosong, tidak ada nama ganda, dan semua koordinat berada di dalam kotak batas Bali. Masalahnya ada di asal-usul data. Tidak ada satu pun kolom sumber, tanggal verifikasi, alamat, atau lisensi, padahal blueprint menjadikan keempatnya syarat terbit. Karena itu seluruh baris masuk sebagai draf dan tidak ada yang bisa terbit sebelum editor melengkapinya.

Hasil uji pemetaan: 100 tempat dan 50 hotel dibuat, 0 baris error, 54 baris berperingatan (40 bernada promosi, 18 berkoordinat identik dari 9 pasang, 1 tema yang diabaikan), dan 150 slug unik tanpa penyesuaian.

## Isi dataset

Hotel 50, Wisata 50, Kuliner 25, Kafe 25. Hotel berharga 700 ribu sampai 12 juta rupiah. Sebaran menurut wilayah administratif setelah pemetaan:

Badung 41 tempat dan 28 hotel. Gianyar 25 tempat dan 13 hotel. Klungkung 10 tempat. Denpasar 6 tempat dan 3 hotel. Karangasem 6 tempat dan 2 hotel. Bangli 5 tempat. Tabanan 4 tempat. Buleleng 3 tempat dan 4 hotel. Jembrana tidak punya satu pun baris.

Dua kabupaten, Badung dan Gianyar, memuat 66 dari 100 tempat. Ini penting untuk planner: blueprint menyatakan wilayah harus lebih menentukan hasil daripada popularitas. Dengan data seperti ini, rencana di Bali utara, timur, dan barat akan sangat tipis.

## Temuan

Koordinat. Semua 150 koordinat hanya 4 desimal (sekitar 11 meter) dan banyak yang berupa angka bulat. Ini tanda perkiraan, bukan hasil geocoding. Ada 9 pasang tempat berbeda dengan koordinat identik. Pasangan yang paling mencurigakan adalah Apurva Kempinski (baris 21) dan Kempinski Nusa Dua (baris 43), yang kemungkinan besar properti yang sama dan tercatat dua kali. Pasangan lain: The Edge Bali dengan The Cave by Chef Ryan Clift, Bisma Eight dengan Pison Coffee, Pantai Suluban dengan Single Fin, Hujan Locale dengan Clear Cafe, Crate Cafe dengan Blacklist Coffee Roasters, Menega Cafe dengan Dripz Coffee, Warung Mak Beng dengan Mahagiri Coffee, serta Massimo dengan Genius Cafe. Bisa jadi satu kompleks, bisa jadi koordinat disalin. Penanda di peta dan rute akan meleset sampai koordinat diperiksa.

Teks. Kolom historical_value berisi deskripsi suasana, bukan fakta sejarah, jadi dipetakan ke ringkasan. Panjangnya 75 sampai 113 karakter, aman untuk batas. Sebanyak 40 baris memuat kata promosi seperti mewah, megah, ikonik, legendaris, atau surga. Blueprint meminta ringkasan yang spesifik dan dapat ditelusuri. Beberapa klaim juga sangat spesifik dan tanpa sumber, misalnya tebing setinggi 150 meter, peninggalan abad ke-9, dan mata air berumur ribuan tahun. Semuanya perlu diverifikasi sebelum terbit.

Harga. Hotel punya satu angka tanpa sumber, tanggal, dan keterangan apakah itu harga per kamar. Harga pada 100 tempat non-hotel bernilai 0, yang dibaca sebagai "tidak ada data" dan bukan gratis. Dataset juga tidak membedakan resort, villa, atau guesthouse, sehingga semua hotel masuk sebagai jenis "hotel".

Area dan wilayah. Kolom location_area mencampur nama area populer (Ubud, Canggu, Seminyak) dengan nama kabupaten (Karangasem, Gianyar). Ini persis campuran yang diperingatkan blueprint. Pemetaan ke kabupaten dibuat di config/archive.php dan nama area asli disimpan sebagai area_label. Pemetaan itu berdasarkan pengetahuan umum saya dan perlu Anda periksa, terutama untuk Bedugul, Pejarakan, dan Bingin.

Tema. Lima nilai vibe_category dipetakan menjadi tag tema dengan definisi usulan. Nilai "Ritme Padat" hanya muncul satu kali (Waterbom Bali) dan tampak seperti tempo, bukan tema, sehingga sengaja tidak dipasang. "Kuliner Estetik" (39 baris) berbau Instagrammable, dan blueprint meminta tema budaya tidak disamakan dengan itu. Tema pada hotel tidak dipakai karena tag hanya berlaku untuk tempat.

## Yang perlu keputusan Anda

1. Dari mana dataset ini berasal dan apa lisensinya. Kolom atribusi dibiarkan kosong karena saya tidak mau menebak. Blueprint melarang memasukkan dataset berlisensi tanpa izin, jadi berkas ini sengaja tidak saya jadikan fixture tes.
2. Apakah Apurva Kempinski dan Kempinski Nusa Dua memang satu properti.
3. Apakah pemetaan area ke kabupaten sudah benar.
4. Apakah lima definisi tema usulan disetujui atau diubah.
5. Siapa yang mengisi sumber, tanggal verifikasi, foto, dan menulis ulang ringkasan. Itu pekerjaan editorial per tempat, bukan pekerjaan teknis, dan itu yang menentukan kapan situs punya konten terbit.

## Saran praktis

Jangan menargetkan 150 sekaligus. Pilih 20 sampai 30 tempat yang tersebar di sebanyak mungkin kabupaten, termasuk Bangli, Buleleng, Karangasem, dan Tabanan, lalu lengkapi sampai terbit. Itu cukup untuk menguji beranda, filter, peta, dan planner di Fase 4 dengan data yang jujur.

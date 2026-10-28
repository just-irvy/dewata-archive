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

UI berbahasa Indonesia (keputusan terbuka pada FILE 21), nama merek tetap The Dewata Archive. Hanya layout, navigasi, footer, dan halaman auth yang lewat `lang/id/ui.php`. Halaman admin dan halaman publik Fase 2 menulis teks langsung di Blade. Bila lokalisasi ke bahasa lain benar-benar dibutuhkan, teks itu perlu dipindahkan dulu.

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

# Fase 2: penemuan

## Kolom baru

places.featured_at menandai tempat sorotan beranda. Blueprint meminta "satu cerita visual yang kuat" tanpa slider tetapi tidak menjelaskan siapa yang memilihnya. Beranda menampilkan tempat terbit dengan featured_at terbaru. Tempat draf yang disorot tidak tampil. Menyimpan ulang tidak menggeser waktu sorotan.

## Planner disembunyikan

Planner baru ada di Fase 4, padahal blueprint menaruh tombol Plan a Trip di navigasi dan beranda. Konfigurasi archive.features.planner (env ARCHIVE_PLANNER_ENABLED, bawaan false) menyembunyikan semua tautan ke sana, supaya tidak ada tombol yang menuju halaman kosong.

## Bookmark

Guest yang menekan Simpan diarahkan ke halaman masuk lalu dikembalikan ke tempat itu setelah login. Tidak ada modal, dan permintaan GET untuk itu tidak mengubah data. Bookmark sementara di sesi tidak dibuat karena blueprint membolehkan "tidak tersedia" dan itu menghindari data yang hilang diam-diam. Menyimpan bersifat idempotent. Bookmark pada tempat yang kemudian diarsipkan tetap bisa dihapus, tetapi tidak tampil di daftar.

## Aturan filter

Beberapa tema berarti salah satu (OR), supaya katalog kecil tidak langsung kosong. Filter aksesibilitas hanya menghitung data berstatus terverifikasi dan opsinya baru muncul bila ada tempat terbit yang memenuhinya. Semua pilihan filter (wilayah, kategori, tema) hanya memuat nilai yang punya tempat terbit. Filter harga hotel mencocokkan kisaran yang tumpang tindih dengan rentang pengguna, dan hotel tanpa data harga tidak ikut saat filter harga dipakai. Halaman menyatakan hal itu secara eksplisit.

## Pencarian

Pencarian memakai LIKE pada nama dan ringkasan. Cukup untuk ratusan hingga beberapa ribu tempat, tetapi tidak toleran terhadap salah ketik dan tidak mengurutkan menurut relevansi. Ganti dengan fulltext atau mesin pencari bila katalog dan kebutuhan membesar.

## Tampilan

Kartu tempat memakai garis atas, bukan kotak berbayang. Foto di kartu bersifat dekoratif (alt kosong) karena nama tempat ada di sampingnya sebagai tautan. Daftar bernomor hanya dipakai pada koleksi karena urutannya memang bagian dari cerita. Halaman 500 dan 503 berdiri sendiri tanpa layout agar tidak gagal berulang saat database atau aset bermasalah. Footer menelan galat database dan menampilkan "belum ada konten terbit".

## Belum dikerjakan

Peta di halaman detail (Fase 3). Indeks koleksi di /collections karena tidak ada di sitemap blueprint. Sitemap XML, tag Open Graph, dan data terstruktur. Pembatasan laju untuk pencarian publik (Fase 5). Keadaan loading terbatas pada tombol Terapkan dan Simpan yang menandai aria-busy, karena halaman dirender di server dan tidak ada skeleton.

## Yang belum bisa saya pastikan

Tata letak belum pernah dilihat di browser. Kelas Tailwind sudah dicek terbentuk, tetapi jarak, proporsi gambar, dan keterbacaan pada 390 piksel harus Anda lihat sendiri. Desain juga bergantung pada foto sungguhan. Kartu tanpa foto sengaja tidak memakai gambar pengganti, jadi halaman dengan sedikit foto akan tampak lebih padat teks.

# Fase 3: peta dan rute

## Yang terkonfirmasi dari dokumentasi Google

Dicek pada 26 Oktober 2026. Maksimum 25 intermediate waypoint dan tarif lebih tinggi pada 11 ke atas (halaman terakhir diperbarui 17 September 2026), jadi klaim blueprint benar. Field mask wajib dan `legs` harus diminta agar intermediate waypoint ikut. Origin boleh berupa objek lat dan lng. Polyline diperoleh dari `createPolylines()`. Dua hal yang tidak ada di blueprint: moda TWO_WHEELER berstatus beta dan ditagih lebih tinggi, dan Google mewajibkan peringatan di UI untuk rute jalan kaki, sepeda, dan motor. Keduanya sudah diterapkan.

## Keputusan

Map ID wajib, sehingga gaya ink dan sepia dipasang lewat cloud styling, bukan JSON tertanam. Alasannya marker lanjutan mensyaratkan Map ID dan marker lama sudah ditandai deprecated. Ini memilih satu dari dua pendekatan yang diizinkan FILE 11, dan menghindari dua jalur kode.

Peta dimuat malas saat mendekati layar. Halaman tanpa peta tidak menghubungi Google. Tanpa key, komponen tidak merender apa pun.

Gagal tampil jujur: marker tetap tampil bila rute gagal, dan tidak pernah ada garis lurus pengganti. Moda transport umum tidak dirutekan. Batas intermediate ditetapkan 10, satu di bawah ambang tarif tinggi.

Adapter server (Routes API) dibuat sekarang walau pemakainya baru Fase 4 dan Fase 6, karena struktur Services/Maps ada di blueprint dan perlu teruji lebih awal. Respons tidak di-cache karena ketentuan konten Maps. Pengaman biaya: batas harian per hari, timeout 8 detik, dan TRAFFIC_UNAWARE.

## Yang diuji dan yang belum

Tes JavaScript (29, dijalankan di sini dan lulus) mencakup helper dan controller dengan objek Google tiruan. Satu bug nyata tertangkap: durasi atau jarak yang hilang tampil sebagai angka nol. Tes PHP untuk adapter memakai Http::fake. Belum teruji terhadap Google sebenarnya: bentuk `intermediates`, penerimaan routingPreference untuk TWO_WHEELER, dan perilaku gaya bersama Map ID. Daftar lengkap ada di docs/PETA.md.

## Belum dikerjakan

Tampilan peta bergaya ink dan sepia itu sendiri (dikerjakan di Cloud Console oleh Anda). Marker D1·1 dan pemilih hari untuk planner (Fase 4, controller sudah mendukung `update()`). Content-Security-Policy. Pemantauan penggunaan di luar batas harian server: pantau lewat laporan Google Cloud Console dan budget alert.

# Impor dataset

Dikerjakan setelah Fase 3 karena dataset diterima. Temuan atas datasetnya ada di docs/AUDIT-DATASET.md.

## Alur

Mengikuti FILE 16. Unggah (hanya csv atau txt, maksimal 2 MB dan 2000 baris), parse ke tabel staging, validasi, laporan pratinjau, persetujuan admin, lalu rollback. Tabel live tidak tersentuh sebelum persetujuan. Baris yang error tidak diproses, baris lain tetap bisa dibuat setelah admin melihat jumlahnya.

## Aturan yang sengaja dipilih

Impor hanya membuat draf. Dataset tidak memuat sumber maupun tanggal verifikasi, jadi tidak ada baris yang bisa lolos pemeriksaan kesiapan terbit. Ini diuji.

Impor hanya membuat record baru. Baris yang external_id-nya sudah ada dilewati, bukan ditimpa, sehingga kerja editor tidak pernah hilang karena impor ulang. Konsekuensinya, memperbarui data yang sudah ada belum didukung.

Rollback hanya untuk super-admin dan hanya menghapus permanen draf yang belum diubah sejak diimpor, belum punya foto, dan belum dipakai di rencana atau koleksi. Yang sudah dikerjakan editor dipertahankan dan dilaporkan. Penghapusan permanen dipilih karena external_id bersifat unik, sehingga hapus lunak akan memblokir impor ulang.

Pemetaan tidak menebak. Area, kategori, dan koordinat yang tidak dikenal menjadi error. Tema yang tidak dikenal menjadi peringatan dan tag tidak dipasang.

## Perubahan skema

places.area_label dan hotels.area_label menyimpan nama area populer (Ubud, Canggu). Wilayah administratif tetap di region_id. Alternatifnya adalah membuat wilayah editorial per area, tetapi itu membuat Ubud dan Gianyar saling tumpang tindih sebagai dua pintu masuk untuk tempat yang sama. hotels.external_id dan hotels.attribution ditambahkan. places.external_id dan hotels.external_id dibuat unik untuk menjamin impor idempoten.

## Yang tidak ada

Pembaruan record yang sudah ada lewat impor. Perintah artisan untuk impor dari baris perintah. Penyimpanan berkas asli (hanya hash dan nama berkas). Pemetaan area ke wilayah lewat UI (saat ini di config/archive.php).

# Dataset v2

Dibuat dari tiga sumber mentah (docs/KURASI-DATASET-V2.md). Importer sekarang mendukung dua bentuk berkas. Format v1 (location_area, price_per_night, vibe_category, historical_value) tetap bisa dibaca. Format v2 menambah kabupaten, price_min, price_max, price_source, theme, hotel_type, source_url, source_date, dan summary.

Ringkasan kosong sekarang berupa peringatan, bukan error, dan nilainya dibiarkan kosong. Pemeriksaan kesiapan terbit sudah memblokir ringkasan kosong, sehingga tempat tanpa teks tidak mungkin terbit. Ini lebih aman daripada mengisinya dengan kalimat templat yang tampak seperti konten.

Jenis sumber harga ditentukan dari kolom price_source (Traveloka menjadi data penyedia, Bali Destinations menjadi estimasi). Status verifikasi harga tetap "Belum diketahui" karena tidak ada tanggal harga di sumber mana pun.

Tautan Google Maps dipakai sebagai source_url untuk wisata. Itu sumber keberadaan dan koordinat, bukan verifikasi. verified_at tetap kosong sampai editor memeriksanya.

Skrip kurasi ada di tools/curate_dataset.py dan butuh pandas dan numpy. Berkas CSV hasilnya sengaja tidak ikut repository karena asal dan lisensi datanya belum jelas.

# Terbit massal

Menerbitkan satu per satu terlalu lambat untuk ratusan draf hasil impor, jadi daftar tempat dan hotel kini punya kolom kesiapan, kotak centang, dan dua tombol: terbitkan yang dipilih, dan terbitkan semua draf yang siap menurut filter aktif.

Terbit massal tidak punya jalur khusus. Setiap record melewati ChangeStatus yang sama dengan terbit tunggal, sehingga pemeriksaan kesiapan, otorisasi per record, dan audit log tidak berubah. Yang gagal dilewati, tidak dipaksa. Alasan kegagalan dikelompokkan per pesan agar laporan berguna sebagai daftar kerja, bukan 150 baris galat.

Batas 300 draf per klik menghindari permintaan yang terlalu lama. Hanya draf yang diproses, sehingga yang sudah terbit atau diarsipkan tidak tersentuh. Menurunkan atau mengarsipkan massal sengaja tidak ada.

Konsekuensi yang perlu disadari: terbit massal mempermudah menerbitkan konten yang belum ditinjau manusia. Status verifikasi pada data (Terverifikasi atau Perlu ditinjau) tetap tampil ke publik, jadi isinya harus jujur. Jangan mengisi tanggal verifikasi secara massal tanpa benar-benar memeriksa.

# Impor foto massal

Foto memakai nama berkas sebagai kunci, dengan slug sebagai nama utama dan nama tempat sebagai cadangan. Akhiran -2, -3 menandai foto berikutnya, dan nama utuh didahulukan sebelum akhiran dibaca agar tempat bernama "Pura 2" tidak tertukar. Nama yang cocok dengan lebih dari satu record dilaporkan ambigu dan dilewati.

Hak pakai sengaja tidak punya nilai bawaan. Perintah menolak berjalan tanpa --credit, --license, atau keterangan.csv, karena kredit dan lisensi adalah pernyataan hukum yang harus datang dari pemilik data. Alt text punya nilai bawaan ("Foto {nama}") karena itu tidak mengklaim hal yang salah, tetapi alt text yang baik tetap perlu ditulis untuk foto penting.

Gambar disimpan ulang, bukan disalin, agar metadata lokasi GPS terbuang dan ukuran terkendali. Syaratnya ekstensi exif untuk JPG. Tanpa itu berkas disalin apa adanya, bukan diproses setengah jalan yang bisa memutar foto.

Idempotensi memakai hash isi berkas asli (kolom media.source_hash). Impor foto tidak menyentuh foto yang diunggah lewat editor admin.

Foto belum diuji bersama kode Laravel di mesin Anda. Logika pencocokan dan pemrosesan gambar sudah dijalankan langsung (18 pemeriksaan, termasuk rotasi EXIF dan pembuangan metadata), sedangkan perintah artisan dan tesnya belum dijalankan.

# Pengaman impor: ringkasan templat, sumber palsu, dan klaim verifikasi

Insiden: sebuah berkas hasil edit dari v3 mengisi seluruh 150 baris dengan ringkasan templat. Kalimat keduanya identik di 149 baris ("Menawarkan pengalaman menginap yang nyaman dengan akses strategis...") termasuk untuk kafe, pantai, dan air terjun, sehingga menyatakan hal yang keliru. Semua tautan sumber berupa halaman hasil pencarian Google, semua baris bertanda "Verified" dengan tanggal yang sama, dan tiga baris yang sudah diriset (dengan sumber nyata) tertimpa.

Importer sekarang menolak tiga hal itu. Pertama, tautan hasil pencarian (Google, Bing, DuckDuckGo, Yahoo) dibuang dengan peringatan karena bukan sumber. Kedua, status verifikasi dari berkas diturunkan paling tinggi menjadi "Perlu ditinjau", karena "Terverifikasi" tampil ke publik sebagai klaim pemeriksaan oleh manusia dan hanya boleh ditetapkan editor di editor tempat. Nilai status kini tidak peka huruf besar. Ketiga, kalimat panjang (enam kata atau lebih) yang identik, setelah nama tempat dibuang, di sedikitnya lima baris dan 5 persen batch dianggap ciri templat, dan ringkasan yang memuatnya dikosongkan dengan peringatan.

Perintah `archive:clean-imported` menerapkan aturan yang sama pada data yang sudah masuk. Tanpa --apply hanya simulasi. Dengan --apply, record hasil impor yang terdampak dikosongkan ringkasannya, dibuang tautan pencariannya, dihapus klaim verifikasinya, dan bila sudah terbit ditarik menjadi draf (record terbit tanpa ringkasan melanggar syarat terbit). Hanya record dengan external_id yang disentuh, dan setiap perubahan tercatat di audit log.

Batasan jujur: deteksi templat hanya menangkap kalimat yang berulang. Ringkasan yang ditulis berbeda-beda tetapi sama-sama dikarang tidak terdeteksi. Pemeriksaan ini mengurangi risiko, bukan pengganti peninjauan editor.

# Mengisi hasil riset ke draf yang sudah ada

Impor bersifat hanya-membuat (keputusan awal agar kerja editor tidak pernah tertimpa), tetapi itu membuat berkas yang diperbaiki tidak bisa dipakai setelah draf terlanjur ada. archive:apply-research menutup celah itu tanpa melonggarkan jaminan tadi. Hanya draf diproses, hanya kolom yang kosong diisi, isi editor tidak pernah ditimpa, dan record terbit tidak disentuh. Tanggal dan status verifikasi hanya diisi bila keduanya masih kosong. Berkas memakai pemeta impor yang sama, sehingga semua pengaman impor (templat, tautan pencarian, penurunan status "Terverifikasi") ikut berlaku.

Dataset v4 memuat lima baris teriset dari sembilan kabupaten yang ada: Museum Yadnya (Badung), Penglipuran (Bangli), Jatiluwih (Tabanan), Brahmavihara-Arama (Buleleng), dan Monumen Puputan Klungkung (Klungkung). Angka yang bertentangan antarsumber (tahun berdiri, luas, tanggal) sengaja tidak dimuat dan alasannya tercatat di kolom note.

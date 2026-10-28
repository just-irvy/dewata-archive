<?php

return [

    /*
    | Akun super-admin awal untuk seeder. Kosongkan di production bila akun dibuat
    | manual. Password tidak pernah disimpan di repository.
    */
    'admin' => [
        'name' => env('ARCHIVE_ADMIN_NAME', 'Admin Arsip'),
        'email' => env('ARCHIVE_ADMIN_EMAIL'),
        'password' => env('ARCHIVE_ADMIN_PASSWORD'),
    ],

    /*
    | Kotak batas kasar Pulau Bali beserta Nusa Penida dan Lembongan. Koordinat di luar
    | kotak ini tidak ditolak, hanya diberi peringatan saat publikasi.
    */
    'bounds' => [
        'lat' => [-8.95, -8.0],
        'lng' => [114.4, 115.8],
    ],

    /*
    | Ambang daftar tinjauan (FILE 16, langkah 5). Konten terbit yang terakhir
    | diverifikasi lebih lama dari ambang ini masuk daftar. Angka awal, sesuaikan
    | dengan kebijakan tim konten.
    */
    'review_after_days' => [
        'place' => 365,
        'hotel_price' => 90,
    ],

    /*
    | Fitur yang dibuka bertahap. Planner baru ada di Fase 4, jadi tautan menuju
    | planner tidak ditampilkan sebelum itu.
    */
    'features' => [
        'planner' => (bool) env('ARCHIVE_PLANNER_ENABLED', false),
    ],

    'per_page' => 12,

    // Batas record yang diproses sekali klik pada terbit massal. Sisanya dilaporkan dan bisa diproses lagi.
    'bulk_publish_limit' => 300,

    /*
    | Google Maps Platform (FILE 11).
    | browser_key: dipakai di browser dan tampil di HTML, jadi WAJIB dibatasi referrer HTTP
    |   dan dibatasi ke API yang dipakai (Maps JavaScript API, Routes API).
    | server_key: terpisah, dibatasi IP, dipakai panggilan server (chatbot, snapshot rute).
    | map_id: wajib untuk marker lanjutan. DEMO_MAP_ID cukup untuk pengembangan,
    |   produksi memakai Map ID dengan gaya ink dan sepia (lihat docs/PETA.md).
    | routes_daily_cap: batas permintaan Routes API dari server per hari. Angka awal.
    */
    'maps' => [
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'map_id' => env('GOOGLE_MAPS_MAP_ID', 'DEMO_MAP_ID'),
        'routes_daily_cap' => (int) env('ARCHIVE_ROUTES_DAILY_CAP', 1000),
    ],

    /*
    | Impor dataset (FILE 16). Profil di bawah dibuat untuk combined_dataset_150_rows.
    | Impor hanya membuat draf. Tidak ada baris yang terbit otomatis, karena dataset tidak
    | memuat sumber, tanggal verifikasi, maupun lisensi.
    */
    'import' => [
        'source' => 'combined150',
        'max_rows' => 2000,
        'max_kb' => 2048,

        // Dataset mencampur area populer (Ubud, Canggu) dengan nama kabupaten. Semuanya dipetakan
        // ke batas administratif. Nama area asli disimpan sebagai area_label. Pemetaan ini
        // berdasarkan pengetahuan umum dan perlu diperiksa tim konten.
        'area_regions' => [
            'Ubud' => 'gianyar', 'Gianyar' => 'gianyar',
            'Seminyak' => 'badung', 'Canggu' => 'badung', 'Uluwatu' => 'badung', 'Jimbaran' => 'badung',
            'Nusa Dua' => 'badung', 'Kuta' => 'badung', 'Bingin' => 'badung',
            'Sanur' => 'denpasar', 'Denpasar' => 'denpasar',
            'Karangasem' => 'karangasem', 'Manggis' => 'karangasem', 'Candidasa' => 'karangasem',
            'Tabanan' => 'tabanan', 'Bedugul' => 'tabanan',
            'Bangli' => 'bangli', 'Kintamani' => 'bangli',
            'Klungkung' => 'klungkung', 'Nusa Penida' => 'klungkung', 'Nusa Lembongan' => 'klungkung',
            'Buleleng' => 'buleleng', 'Singaraja' => 'buleleng', 'Munduk' => 'buleleng', 'Pejarakan' => 'buleleng',
        ],

        // Nama kabupaten (kolom `kabupaten`, format v2) -> slug wilayah.
        'kabupaten_regions' => [
            'Badung' => 'badung', 'Bangli' => 'bangli', 'Buleleng' => 'buleleng', 'Denpasar' => 'denpasar', 'Kota Denpasar' => 'denpasar',
            'Gianyar' => 'gianyar', 'Jembrana' => 'jembrana', 'Karangasem' => 'karangasem', 'Klungkung' => 'klungkung', 'Tabanan' => 'tabanan',
        ],

        // Sumber harga (kolom `price_source`) -> jenis sumber harga di arsip. Harga tanpa sumber dikenal dicatat sebagai estimasi.
        'price_sources' => ['Traveloka' => 'provider', 'Bali Destinations' => 'estimate'],

        'hotel_types' => ['hotel', 'resort', 'villa', 'guesthouse', 'boutique'],

        // Kategori kasar dari dataset. Penyempurnaan (pura, pantai, air terjun) dikerjakan editor.
        'hotel_categories' => ['Hotel'],
        'place_categories' => [
            'Wisata' => ['slug' => 'wisata', 'name' => 'Wisata'],
            'Kuliner' => ['slug' => 'kuliner', 'name' => 'Kuliner'],
            'Kafe' => ['slug' => 'kafe', 'name' => 'Kafe'],
            // Format v2 membawa jenis wisata dari label Google Maps. Label itu tidak selalu konsisten.
            'Wisata Alam' => ['slug' => 'wisata-alam', 'name' => 'Wisata Alam'],
            'Wisata Budaya' => ['slug' => 'wisata-budaya', 'name' => 'Wisata Budaya'],
            'Wisata Rekreasi' => ['slug' => 'wisata-rekreasi', 'name' => 'Wisata Rekreasi'],
            'Wisata Umum' => ['slug' => 'wisata-umum', 'name' => 'Wisata Umum'],
        ],

        // vibe_category menjadi tag tema pada tempat. Nilai null berarti sengaja tidak dipasang.
        // Definisi di bawah adalah usulan dan harus disetujui tim konten.
        'vibe_tags' => [
            'Alam Tersembunyi' => ['slug' => 'alam-tersembunyi', 'name' => 'Alam Tersembunyi', 'description' => 'Tempat yang suasananya didominasi alam dan jauh dari keramaian, seperti lembah, hutan, atau sawah.'],
            'Pesisir' => ['slug' => 'pesisir', 'name' => 'Pesisir', 'description' => 'Tempat di tepi laut, tebing pantai, atau pulau kecil.'],
            'Desa & Seni' => ['slug' => 'desa-seni', 'name' => 'Desa dan Seni', 'description' => 'Tempat yang berkaitan dengan kehidupan desa, kerajinan, atau seni pertunjukan dan seni rupa.'],
            'Pura & Sejarah' => ['slug' => 'pura-sejarah', 'name' => 'Pura dan Sejarah', 'description' => 'Tempat bernilai keagamaan atau sejarah, seperti pura, situs, dan bangunan lama.'],
            'Kuliner Estetik' => ['slug' => 'kuliner-estetik', 'name' => 'Kuliner Estetik', 'description' => 'Tempat makan dan minum yang dipilih karena suasana dan tampilannya, bukan hanya menunya.'],
            // Tema format v2, diturunkan dari kata pada nama dan kategori sumber.
            'Pantai' => ['slug' => 'pantai', 'name' => 'Pantai', 'description' => 'Tempat di tepi pantai atau teluk.'],
            'Pura dan Arsitektur' => ['slug' => 'pura-arsitektur', 'name' => 'Pura dan Arsitektur', 'description' => 'Pura, puri, dan bangunan tradisional yang menjadi daya tarik utamanya.'],
            'Warisan Budaya' => ['slug' => 'warisan-budaya', 'name' => 'Warisan Budaya', 'description' => 'Tempat yang menyimpan tradisi atau sejarah setempat, seperti museum, monumen, dan desa adat.'],
            'Alam' => ['slug' => 'alam', 'name' => 'Alam', 'description' => 'Tempat yang daya tariknya adalah alam, seperti air terjun, hutan, danau, dan perbukitan.'],
            'Rekreasi' => ['slug' => 'rekreasi', 'name' => 'Rekreasi', 'description' => 'Tempat rekreasi dan hiburan, seperti taman dan wahana air.'],
            'Kuliner' => ['slug' => 'kuliner', 'name' => 'Kuliner', 'description' => 'Tempat makan.'],
            'Kopi' => ['slug' => 'kopi', 'name' => 'Kopi', 'description' => 'Kedai kopi dan kafe.'],
            // Satu-satunya baris ini (Waterbom Bali) memakai nilai yang tampak seperti tempo, bukan tema.
            'Ritme Padat' => null,
        ],

        // Kata bernada promosi. Teks yang memuatnya diberi peringatan agar ditulis ulang secara spesifik.
        'promo_words' => ['mewah', 'eksklusif', 'ikonik', 'megah', 'dramatis', 'legendaris', 'terbaik', 'sempurna', 'surga', 'magis', 'memukau', 'menakjubkan', 'elegan', 'absolut', 'mahakarya', 'brilian'],
    ],

    // Folder tempat Anda menaruh foto untuk dipasang dengan `php artisan archive:import-photos`.
    // Isinya subfolder places/ dan hotels/, berkas bernama sesuai slug atau nama (museum-yadnya.jpg).
    'photos' => [
        'import_path' => storage_path('app/photos-import'),
    ],

    'media' => [
        'disk' => 'public',
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'weekdays' => [
        'mon' => 'Senin',
        'tue' => 'Selasa',
        'wed' => 'Rabu',
        'thu' => 'Kamis',
        'fri' => 'Jumat',
        'sat' => 'Sabtu',
        'sun' => 'Minggu',
    ],

    /*
    | Atribut aksesibilitas yang boleh diisi. Nilai: yes, no, unknown. Isi hanya bila
    | ada sumber. Jangan menebak (FILE 11).
    */
    'accessibility_attributes' => [
        'step_free' => 'Akses tanpa tangga',
        'family_friendly' => 'Ramah keluarga',
        'limited_mobility' => 'Cocok untuk mobilitas terbatas',
    ],

];

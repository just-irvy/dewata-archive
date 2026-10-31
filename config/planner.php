<?php

/*
| Perencana perjalanan (FILE 07 sampai FILE 10).
| Semua angka di sini adalah aturan produk, bukan konstanta yang tersebar di controller.
| Ubah di sini lalu jalankan `php artisan test` karena beberapa tes membaca nilai ini.
*/

return [

    // Rentang durasi (FILE 07, step 1).
    'max_days' => 14,

    // Tema utama yang boleh dipilih (FILE 07, step 2).
    'max_themes' => 5,

    // Batas tempat per hari. Sama dengan RouteQuery::MAX_INTERMEDIATES agar satu hari
    // selalu muat dalam satu permintaan rute di bawah ambang tarif tinggi Google.
    'max_items_per_day' => 10,

    // Draf tamu berlaku sekian hari sejak dibuat atau terakhir diubah. Setelah itu dihapus
    // oleh `archive:purge-guest-drafts` (dijadwalkan harian).
    'guest_ttl_days' => (int) env('ARCHIVE_GUEST_DRAFT_DAYS', 7),

    // Draf tamu aktif per browser. Yang paling lama dihapus bila batas terlampaui.
    'guest_max_drafts' => 5,

    // Versi aturan rekomendasi. Naikkan bila bobot atau algoritma berubah, agar rencana lama
    // tetap bisa ditelusuri ke aturan yang menghasilkannya.
    'recommendation_version' => 'rules-1',

    // Durasi kunjungan bila tempat belum punya typical_visit_minutes. Hanya untuk menghitung
    // kepadatan hari, tidak ditampilkan sebagai durasi tempat.
    'default_visit_minutes' => 90,

    // Jeda antar tempat untuk makan, istirahat, dan pindah (FILE 09, langkah 5).
    'buffer_minutes' => 45,

    // Kepadatan hari menurut pace (FILE 08, step 4). day_minutes adalah waktu di tempat ditambah
    // jeda antar tempat. Dengan kunjungan bawaan 90 menit dan jeda 45 menit: santai muat 3 tempat
    // (6 jam), seimbang 4 tempat (8,5 jam), padat 5 tempat (11 jam). Padat bisa mencapai 6 bila
    // tempatnya punya durasi kunjungan lebih pendek.
    'pace' => [
        'relaxed' => ['max_stops' => 3, 'day_minutes' => 360],
        'balanced' => ['max_stops' => 4, 'day_minutes' => 510],
        'packed' => ['max_stops' => 6, 'day_minutes' => 660],
    ],

    // Waktu harian menambah atau mengurangi satu pemberhentian dan satu jam kunjungan.
    'daily_time' => [
        'early' => 1,
        'flexible' => 0,
        'not_late' => -1,
    ],

    // Bobot skor (FILE 09, langkah 3).
    'weights' => [
        'theme' => 3.0,        // proporsi tema terpilih yang cocok
        'proximity' => 2.0,    // dekat titik awal (garis lurus, hanya untuk peringkat internal)
        'cluster' => 2.5,      // penalti menjauh dari tempat sebelumnya pada hari yang sama
        'variety' => 1.5,      // penalti kategori yang sama berulang pada satu hari
        'verified' => 0.5,     // tempat yang sudah diverifikasi editor
        'featured' => 0.5,     // tempat sorotan editor
        'outdoor' => 2.0,      // penalti tema luar ruang bila pengguna memintanya
    ],

    // Radius garis lurus untuk mengelompokkan satu hari. Hanya dipakai internal untuk peringkat
    // kedekatan, tidak pernah ditampilkan sebagai jarak jalan (FILE 09, fallback behavior).
    'radius_km' => [
        'nearby' => 18,
        'willing' => 40,
    ],

    // Tag tema yang dianggap dominan luar ruang untuk opsi "kurangi aktivitas luar ruang".
    // Ini preferensi peringkat, bukan klaim tentang tempat. Sesuaikan dengan tim konten.
    'outdoor_tags' => ['alam', 'alam-tersembunyi', 'pantai', 'pesisir'],

    'labels' => [
        'party' => [
            'solo' => 'Sendiri',
            'couple' => 'Berdua',
            'family' => 'Keluarga',
            'friends' => 'Teman',
            'group' => 'Rombongan',
        ],
        'flexibility' => [
            'nearby' => 'Tetap di sekitar wilayah yang dipilih',
            'willing' => 'Bersedia menempuh perjalanan lebih jauh',
        ],
        'daily_time' => [
            'early' => 'Mulai pagi',
            'flexible' => 'Fleksibel',
            'not_late' => 'Tidak sampai terlalu malam',
        ],
    ],
];

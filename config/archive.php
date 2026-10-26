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

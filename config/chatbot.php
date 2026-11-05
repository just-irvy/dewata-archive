<?php

/*
| Asisten chat Gemini (FILE 17, Fase 6).
| Semua angka di sini adalah aturan produk. Kunci API TIDAK pernah ada di file ini:
| ia dibaca dari config/services.php (GEMINI_API_KEY di .env) dan hanya dipakai server.
*/

return [

    // Saklar utama. Widget dan endpoint /chat mati total bila false atau kunci Gemini kosong.
    'enabled' => (bool) env('ARCHIVE_CHATBOT_ENABLED', false),

    // Batas input (FILE 15: abuse dan biaya).
    'max_message_chars' => 500,
    'max_history_messages' => 10,
    'max_history_chars' => 1200,

    // Percakapan tersimpan, HANYA untuk pengguna yang login (tamu tidak pernah disimpan).
    // retention_days: pesan lebih tua dihapus archive:purge-chat (dijadwalkan harian).
    // widget_messages: jumlah pesan terakhir yang dimuat ulang di widget setiap halaman.
    // max_stored_messages: batas pesan per akun; yang lebih lama dihapus saat pesan baru masuk.
    'retention_days' => (int) env('ARCHIVE_CHATBOT_RETENTION_DAYS', 30),
    'widget_messages' => 30,
    'max_stored_messages' => 200,

    // Berapa kali model boleh memanggil tool dalam satu pertanyaan.
    'max_tool_rounds' => 4,

    // Anggaran waktu per pertanyaan (detik). Bila putaran model dan tool sudah melewatinya, orkestrator
    // berhenti memanggil tool dan meminta jawaban akhir dari data yang sudah terkumpul.
    'time_budget_seconds' => (int) env('ARCHIVE_CHATBOT_TIME_BUDGET', 35),

    // Jumlah hasil maksimum per pemanggilan tool pencarian.
    'results_limit' => 5,

    // Pembatasan laju. Kunci: user bila login, IP bila tamu. global_daily_cap menghentikan
    // seluruh chatbot bila total pertanyaan sehari melewati batas (pengaman tagihan).
    'rate' => [
        'per_minute' => (int) env('ARCHIVE_CHATBOT_PER_MINUTE', 8),
        'per_day_user' => (int) env('ARCHIVE_CHATBOT_PER_DAY_USER', 100),
        'per_day_guest' => (int) env('ARCHIVE_CHATBOT_PER_DAY_GUEST', 40),
        'global_daily_cap' => (int) env('ARCHIVE_CHATBOT_DAILY_CAP', 2000),
    ],

    'greeting' => 'Halo, saya asisten arsip. Saya bisa mencarikan tempat wisata dan hotel dari katalog The Dewata Archive, termasuk hotel sesuai kisaran harga per malam yang Anda mau. Mau mulai dari mana?',

    // Tombol pertanyaan cepat di awal percakapan.
    'suggestions' => [
        'Rekomendasikan wisata alam di Ubud',
        'Hotel di bawah Rp700.000 per malam',
        'Tempat budaya dan pura yang cocok untuk setengah hari',
        'Bagaimana cara menyusun rencana di sini?',
    ],

    /*
    | Basis pengetahuan FAQ. Jawaban tentang situs dan hal-hal umum yang stabil.
    | Tim konten boleh mengubah isi ini tanpa menyentuh kode. JANGAN menaruh harga, jam buka,
    | tarif tiket, kurs, aturan visa, atau cuaca di sini: data yang cepat basi harus datang
    | dari katalog (lewat tool) atau dijawab "cek sumber resmi".
    */
    'faq' => [
        'Apakah bisa memesan hotel atau membayar di sini?' => 'Tidak. The Dewata Archive hanya untuk menemukan tempat dan menyusun rencana. Tidak ada pemesanan, pembayaran, atau informasi ketersediaan kamar. Untuk memesan, pengguna perlu membuka situs resmi hotel atau penyedia pemesanan.',
        'Apakah harus punya akun?' => 'Tidak. Tamu bisa menjelajah dan mencoba planner. Akun hanya perlu untuk menyimpan rencana secara permanen, menyimpan tempat (bookmark), dan membukanya dari perangkat lain.',
        'Bagaimana cara menyusun rencana perjalanan?' => 'Buka halaman Planner. Ada dua cara: "Get recommendations" (menjawab beberapa pertanyaan lalu sistem menyusun draf) atau "Build manually" (memilih sendiri tempat dan urutannya). Keduanya bisa diedit setelah dibuat.',
        'Apakah ada perhitungan total biaya liburan?' => 'Tidak. Satu-satunya angka uang di situs ini adalah kisaran harga hotel per kamar per malam. Biaya makan, transportasi, dan tiket sengaja tidak dihitung.',
        'Dari mana data harga hotel berasal?' => 'Dari data terkurasi, data penyedia, atau estimasi. Setiap harga menyebut jenis sumbernya dan tanggal terakhir diperiksa. Harga bisa berubah, jadi cek ulang sebelum menginap.',
        'Apakah jarak dan waktu tempuh akurat?' => 'Jarak rute berasal dari Google Maps mengikuti jalan, dan waktu tempuh hanya estimasi. Lalu lintas, penutupan jalan, dan cuaca bisa mengubahnya.',
        'Apa yang perlu diperhatikan saat mengunjungi pura?' => 'Secara umum, berpakaian sopan dan tertutup (banyak pura meminta kain atau selendang), ikuti arahan petugas atau pemangku setempat, dan jangan memasuki area yang ditutup untuk umum. Aturan tiap pura bisa berbeda, jadi cek ketentuan setempat.',
        'Apa itu Nyepi dan apakah memengaruhi rencana?' => 'Nyepi adalah Hari Raya di Bali ketika aktivitas luar ruang dan lalu lintas dihentikan sehari penuh. Tanggalnya berubah tiap tahun, jadi pengguna perlu mengecek kalender resmi sebelum menetapkan tanggal perjalanan.',
    ],
];

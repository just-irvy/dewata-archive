<x-layouts.app title="Tentang arsip">
    <article class="max-w-[68ch] space-y-12">
        <header>
            <h1 class="text-4xl sm:text-5xl">Tentang arsip</h1>
            <p class="mt-4 text-lg leading-relaxed text-ink-soft">
                The Dewata Archive adalah panduan Bali yang dikurasi. Situs ini membantu menemukan tempat dan menyusun rencana perjalanan. Situs ini tidak menjual kamar, tiket, atau paket.
            </p>
        </header>

        <section id="sumber-data" class="scroll-mt-8 space-y-3">
            <h2 class="text-2xl">Sumber data</h2>
            <p>Setiap tempat dan hotel mencantumkan tautan sumber dan tanggal verifikasi. Data yang bisa berubah, yaitu jam buka, harga, fasilitas, dan akses, memiliki status sendiri.</p>
            <dl class="space-y-2">
                <div><dt class="inline font-semibold">Terverifikasi.</dt> <dd class="inline">Sudah dicek terhadap sumber pada tanggal yang tertera.</dd></div>
                <div><dt class="inline font-semibold">Perlu ditinjau.</dt> <dd class="inline">Sudah pernah dicek, tetapi sudah lama atau ditandai berubah.</dd></div>
                <div><dt class="inline font-semibold">Belum diketahui.</dt> <dd class="inline">Tidak ada data yang bisa dipertanggungjawabkan. Situs tidak menebak.</dd></div>
            </dl>
            <p>Harga hotel adalah kisaran per kamar per malam dan menyebut jenis sumbernya: terkurasi, penyedia, atau estimasi. Ketersediaan kamar tidak ditampilkan.</p>
            <p>Jarak dan waktu tempuh adalah estimasi dari Google Maps. Lalu lintas, penutupan jalan, dan cuaca dapat mengubahnya.</p>
        </section>

        <section id="perencana" class="scroll-mt-8 space-y-3">
            <h2 class="text-2xl">Cara perencana menyusun rekomendasi</h2>
            <p>Rekomendasi disusun dari aturan tetap, bukan AI. Arsip memilih tempat terbit yang cocok dengan wilayah, tema, dan kebutuhan mobilitas Anda, lalu mengelompokkan tempat yang berdekatan ke hari yang sama agar tidak bolak-balik. Jumlah tempat per hari mengikuti tempo yang Anda pilih.</p>
            <p>Setiap tempat yang direkomendasikan disertai alasan singkat. Bila tempat yang cocok tidak cukup, arsip menyatakannya dan tidak menggandakan tempat. Planner tidak menghitung biaya perjalanan.</p>
        </section>

        <section id="privasi" class="scroll-mt-8 space-y-3">
            <h2 class="text-2xl">Privasi</h2>
            <p>Akun hanya meminta nama dan email. Tidak ada tanggal lahir, nomor telepon, atau data lain yang tidak dipakai.</p>
            <p>Perencana dapat dicoba tanpa akun. Draf tamu dibuka lewat kode acak di cookie terenkripsi pada browser Anda. Server hanya menyimpan sidik kode itu, bukan kodenya. Draf tamu berlaku {{ config('planner.guest_ttl_days') }} hari sejak terakhir diubah, lalu dihapus otomatis. Saat Anda masuk atau mendaftar dari browser yang sama, draf itu dipindah ke akun Anda.</p>
            <p>Rencana yang disimpan ke akun hanya dapat dibuka oleh pemiliknya. Admin arsip tidak memiliki akses ke rencana pengguna.</p>
            <p>Peta dan rute disediakan Google Maps Platform. Saat peta dimuat, browser Anda berkomunikasi dengan Google dan mengirim koordinat tempat pada hari yang ditampilkan untuk menghitung rute.</p>
            <p>Cookie yang dipakai: cookie sesi untuk masuk dan keamanan formulir, serta cookie draf tamu bila Anda membuat rencana tanpa akun. Tidak ada cookie iklan atau pelacakan.</p>
        </section>
    </article>
</x-layouts.app>

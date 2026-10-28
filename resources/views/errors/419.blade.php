<x-layouts.app title="Sesi berakhir">
    <div class="max-w-xl">
        <p class="typewriter">Kode 419</p>
        <h1 class="mt-2 text-4xl sm:text-5xl">Sesi berakhir</h1>
        <p class="mt-4 text-ink-soft">Halaman terlalu lama terbuka. Muat ulang halaman, lalu kirim ulang formulirnya.</p>
        <p class="mt-8"><a class="btn btn-primary" href="{{ url()->previous(route('home')) }}">Kembali dan muat ulang</a></p>
    </div>
</x-layouts.app>

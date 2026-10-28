<x-layouts.app title="Halaman tidak ditemukan">
    <div class="max-w-xl">
        <p class="typewriter">Kode 404</p>
        <h1 class="mt-2 text-4xl sm:text-5xl">Halaman tidak ditemukan</h1>
        <p class="mt-4 text-ink-soft">Tautan mungkin salah, atau tempat itu belum atau tidak lagi terbit.</p>
        <div class="mt-8 flex flex-wrap items-center gap-4">
            <x-button :href="route('places.index')">Jelajahi arsip</x-button>
            <a class="btn btn-link" href="{{ route('home') }}">Ke beranda</a>
        </div>
    </div>
</x-layouts.app>

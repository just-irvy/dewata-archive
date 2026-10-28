<x-layouts.app title="Tempat tersimpan">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('account.index') }}">Akun</a></nav>
    <h1 class="mt-3 text-4xl">Tempat tersimpan</h1>

    @if ($places->isEmpty())
        <div class="mt-10 max-w-lg border-t border-brass/60 pt-6">
            <h2 class="text-2xl">Belum ada tempat tersimpan</h2>
            <p class="mt-2 text-ink-soft">Tekan Simpan pada tempat yang ingin Anda buka lagi nanti.</p>
            <p class="mt-4"><x-button :href="route('places.index')" variant="secondary">Jelajahi arsip</x-button></p>
        </div>
    @else
        <ul class="mt-10 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($places as $place)
                <li><x-place-card :place="$place" :saved="true" /></li>
            @endforeach
        </ul>
        <div class="mt-12">{{ $saved->links() }}</div>
    @endif
</x-layouts.app>

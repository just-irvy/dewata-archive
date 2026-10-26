<x-layouts.app :title="$title">
    <h1 class="text-4xl">{{ $title }}</h1>
    <p class="mt-4 max-w-[62ch] text-ink-soft">
        Halaman ini belum dibuat. Jadwalnya masuk Fase {{ $phase }} pada rencana implementasi.
    </p>
    <p class="mt-6"><a class="btn btn-link" href="{{ route('home') }}">{{ __('ui.nav.home') }}</a></p>
</x-layouts.app>

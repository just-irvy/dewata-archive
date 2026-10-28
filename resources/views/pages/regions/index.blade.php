<x-layouts.app title="Wilayah" description="Wilayah Bali dalam arsip: kabupaten, kota, dan area editorial.">
    <header class="max-w-2xl">
        <h1 class="text-4xl sm:text-5xl">Wilayah</h1>
        <p class="mt-3 text-ink-soft">Pintu masuk menurut tempat. Wilayah hanya muncul bila sudah punya tempat terbit.</p>
    </header>

    @if ($administrative->isEmpty() && $editorial->isEmpty())
        <div class="mt-12 max-w-lg border-t border-brass/60 pt-6">
            <h2 class="text-2xl">Belum ada wilayah dengan tempat terbit</h2>
            <p class="mt-2 text-ink-soft">Wilayah muncul setelah ada tempat yang terbit di dalamnya.</p>
        </div>
    @endif

    @foreach ([['Kabupaten dan kota', 'Mengikuti batas administratif Provinsi Bali.', $administrative], ['Area editorial', 'Pengelompokan oleh tim arsip. Batasnya tidak selalu sama dengan batas kabupaten.', $editorial]] as [$title, $note, $group])
        @if ($group->isNotEmpty())
            <section class="mt-14 border-t border-brass/60 pt-6" aria-label="{{ $title }}">
                <h2 class="text-2xl">{{ $title }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ $note }}</p>
                <ul class="mt-6 divide-y divide-ink/20 border-y border-ink/20">
                    @foreach ($group as $region)
                        <li class="grid gap-1 py-4 sm:grid-cols-[14rem_1fr_auto] sm:items-baseline sm:gap-6">
                            <a class="btn btn-link justify-start font-display text-xl font-bold no-underline hover:underline" href="{{ route('regions.show', $region->slug) }}">{{ $region->name }}</a>
                            <p class="line-clamp-2 text-[0.9375rem] text-ink-soft">{{ $region->description }}</p>
                            <p class="typewriter">{{ $region->places_count }} tempat</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach
</x-layouts.app>

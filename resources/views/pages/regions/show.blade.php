<x-layouts.app :title="$region->name" :description="$region->description ?: 'Tempat dan penginapan di '.$region->name.'.'">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('regions.index') }}">Wilayah</a></nav>

    <header class="mt-3 max-w-2xl">
        <h1 class="text-4xl sm:text-5xl">{{ $region->name }}</h1>
        @if ($region->description)
            <p class="mt-4 text-lg leading-relaxed text-ink-soft">{{ $region->description }}</p>
        @endif
        @if ($region->region_type->value === 'editorial')
            <p class="typewriter mt-4">Area editorial. Batasnya ditetapkan tim arsip dan tidak selalu sama dengan batas kabupaten.</p>
        @endif
    </header>

    <section aria-labelledby="tempat" class="mt-14 border-t border-brass/60 pt-6">
        <h2 id="tempat" class="text-2xl">Tempat di {{ $region->name }}</h2>

        @if (\App\Support\MapMarkers::enabled() && $places->isNotEmpty())
            <x-map :markers="\App\Support\MapMarkers::forPlaces($places)" :label="'Peta tempat di '.$region->name" class="mt-6" />
            <p class="typewriter mt-2">Peta menampilkan tempat pada halaman ini. Klik penanda untuk menuju kartunya.</p>
        @endif

        <ul class="mt-8 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($places as $place)
                <li><x-dossier-card :place="$place" :saved="in_array($place->id, $saved, true)" /></li>
            @endforeach
        </ul>
        <div class="mt-12">{{ $places->links() }}</div>
    </section>

    @if ($hotels->isNotEmpty())
        <section aria-labelledby="menginap" class="mt-16 border-t border-brass/60 pt-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="menginap" class="text-2xl">Penginapan di {{ $region->name }}</h2>
                <a class="btn btn-link" href="{{ route('hotels.index', ['region' => $region->slug]) }}">Lihat semua</a>
            </div>
            <div class="mt-4">
                @foreach ($hotels as $hotel)
                    <x-hotel-row :hotel="$hotel" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>

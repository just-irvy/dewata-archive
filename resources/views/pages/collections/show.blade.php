@php($cover = $collection->coverMedia?->isPublishable() ? $collection->coverMedia : null)

<x-layouts.app :title="$collection->title" :description="$collection->dek">
    <header class="max-w-3xl">
        <h1 class="text-4xl sm:text-5xl">{{ $collection->title }}</h1>
        @if ($collection->dek)
            <p class="mt-4 text-xl leading-relaxed text-ink-soft">{{ $collection->dek }}</p>
        @endif
    </header>

    @if ($cover)
        <figure class="mt-10">
            <img src="{{ $cover->url() }}" alt="{{ $cover->alt_text }}" @if ($cover->width) width="{{ $cover->width }}" height="{{ $cover->height }}" @endif class="h-auto w-full max-h-[32rem] object-cover">
            @if ($cover->credit)<figcaption class="typewriter mt-2">Foto: {{ $cover->credit }}</figcaption>@endif
        </figure>
    @endif

    @if (\App\Support\MapMarkers::enabled() && $places->isNotEmpty())
        <x-map :markers="\App\Support\MapMarkers::forPlaces($places, numbered: true)" :label="'Peta koleksi '.$collection->title" class="mt-10" />
    @endif

    {{-- Urutan adalah bagian dari cerita koleksi, jadi memakai daftar bernomor. --}}
    <ol class="mt-14 space-y-12">
        @foreach ($places as $place)
            @php($hero = $place->heroMedia())
            <li id="map-item-{{ $place->id }}" tabindex="-1" class="grid gap-6 border-t-2 border-ink pt-5 outline-none focus-visible:outline-2 focus-visible:outline-offset-4 md:grid-cols-[14rem_1fr]">
                @if ($hero)
                    <a href="{{ route('places.show', $place->slug) }}" tabindex="-1" aria-hidden="true">
                        <img src="{{ $hero->url() }}" alt="" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="aspect-[4/3] w-full object-cover" loading="lazy">
                    </a>
                @else
                    <span class="hidden md:block"></span>
                @endif
                <div>
                    <p class="typewriter">{{ $loop->iteration }} dari {{ $loop->count }}, {{ $place->region?->name }}</p>
                    <h2 class="mt-1 text-2xl"><a class="hover:text-rust" href="{{ route('places.show', $place->slug) }}">{{ $place->name }}</a></h2>
                    <p class="mt-2 max-w-[60ch] text-ink-soft">{{ $place->summary }}</p>
                    @if ($place->pivot->editor_note)
                        <p class="mt-3 max-w-[60ch] border-l-2 border-brass pl-4 font-display italic">{{ $place->pivot->editor_note }}</p>
                    @endif
                    <div class="mt-2"><x-bookmark-button :place="$place" :saved="in_array($place->id, $saved, true)" /></div>
                </div>
            </li>
        @endforeach
    </ol>
</x-layouts.app>

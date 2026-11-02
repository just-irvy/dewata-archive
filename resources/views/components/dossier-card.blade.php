@props(['place', 'saved' => false])

{{-- Kartu berkas tempat (desain Stitch "Tempat terbaru"). Kode, koordinat, dan cap berasal dari data. --}}
@php
    $hero = $place->heroMedia();
    $coords = \App\Support\ArchiveCode::dms($place->lat, $place->lng);
    $area = collect([$place->area_label, $place->region?->name])->filter()->unique()->join(', ');
@endphp

<article id="map-item-{{ $place->id }}" tabindex="-1" class="archive-slip flex h-full flex-col outline-none focus-visible:outline-2 focus-visible:outline-offset-4">
    <div class="flex items-center justify-between gap-3 border-b border-ink/20 px-3 py-1.5">
        <span class="kicker text-ink-soft">{{ \App\Support\ArchiveCode::for($place) }}</span>
        <span class="kicker truncate text-ink-soft">{{ $place->region?->name }}</span>
    </div>

    <a href="{{ route('places.show', $place->slug) }}" tabindex="-1" aria-hidden="true" class="m-2 block">
        @if ($hero)
            <img src="{{ $hero->url() }}" alt="" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="aspect-[4/3] w-full object-cover" loading="lazy">
        @else
            {{-- Tanpa foto: lembar kosong bergaris, bukan gambar karangan. --}}
            <span class="flex aspect-[4/3] w-full items-center justify-center bg-paper bg-[repeating-linear-gradient(0deg,transparent,transparent_7px,rgb(177_138_80/0.18)_7px,rgb(177_138_80/0.18)_8px)]">
                <span class="kicker text-ink-soft">Foto belum tersedia</span>
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col px-4 pb-4 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-stamp :status="$place->verification_status" />
            <span class="kicker text-brass-deep">{{ $place->categories->first()?->name }}</span>
        </div>
        <h3 class="mt-3 font-display text-xl font-bold leading-snug">
            <a href="{{ route('places.show', $place->slug) }}" class="hover:text-rust">{{ $place->name }}</a>
        </h3>
        @if ($area)
            <p class="typewriter mt-0.5">{{ $area }}</p>
        @endif
        <p class="mt-2 line-clamp-3 text-[0.9375rem] text-ink-soft">{{ $place->summary }}</p>

        <div class="mt-auto flex items-center justify-between gap-3 border-t border-ink/15 pt-3 mt-4">
            <span class="typewriter text-[0.75rem]">{{ $coords ?? 'Koordinat belum dicatat' }}</span>
            <a class="kicker text-rust hover:underline" href="{{ route('places.show', $place->slug) }}">Pelajari <span aria-hidden="true">→</span><span class="sr-only"> {{ $place->name }}</span></a>
        </div>
        <div class="mt-2 flex justify-end">
            <x-bookmark-button :place="$place" :saved="$saved" />
        </div>
    </div>
</article>

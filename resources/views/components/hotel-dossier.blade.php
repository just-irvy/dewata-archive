@props(['hotel'])

{{-- Kartu penginapan (desain Stitch "Cuplikan penginapan"). Harga selalu per malam dengan jenis sumbernya. --}}
@php
    $hero = $hotel->heroMedia();
    $area = collect([$hotel->area_label ?? null, $hotel->region?->name])->filter()->unique()->join(', ');
@endphp

<article class="archive-frame flex flex-col gap-4 p-4 sm:flex-row sm:p-5">
    <a href="{{ route('hotels.show', $hotel->slug) }}" tabindex="-1" aria-hidden="true" class="block sm:w-2/5 sm:shrink-0">
        @if ($hero)
            <img src="{{ $hero->url() }}" alt="" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="aspect-[4/3] h-full w-full object-cover sm:aspect-auto" loading="lazy">
        @else
            <span class="flex aspect-[4/3] h-full w-full items-center justify-center bg-paper bg-[repeating-linear-gradient(0deg,transparent,transparent_7px,rgb(177_138_80/0.18)_7px,rgb(177_138_80/0.18)_8px)] sm:aspect-auto sm:min-h-44">
                <span class="kicker text-ink-soft">Foto belum tersedia</span>
            </span>
        @endif
    </a>

    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="kicker bg-paper px-2 py-0.5 text-ink">{{ $hotel->hasPrice() ? $hotel->price_basis?->label() : $hotel->type?->label() }}</span>
            <span class="kicker text-moss">{{ $area }}</span>
        </div>
        <h3 class="mt-3 font-display text-xl font-bold leading-snug">
            <a href="{{ route('hotels.show', $hotel->slug) }}" class="hover:text-rust">{{ $hotel->name }}</a>
        </h3>
        <p class="mt-2 line-clamp-3 text-[0.9375rem] text-ink-soft">{{ $hotel->summary }}</p>

        <div class="mt-auto flex flex-wrap items-end justify-between gap-3 border-t border-ink/15 pt-3 mt-4">
            <div>
                <p class="kicker text-ink-soft">Kisaran tarif per malam</p>
                @if ($hotel->hasPrice())
                    @php($fmt = fn ($n) => 'Rp'.number_format($n, 0, ',', '.'))
                    <p class="typewriter mt-0.5 text-[0.9375rem] font-bold text-ink">{{ $fmt($hotel->price_min ?? $hotel->price_max) }}@if ($hotel->price_max && $hotel->price_max !== ($hotel->price_min ?? $hotel->price_max)) sampai {{ $fmt($hotel->price_max) }}@endif</p>
                @else
                    <p class="typewriter mt-0.5 text-ink">Harga tidak tersedia</p>
                @endif
            </div>
            @if ($hotel->hasPrice())
                <x-stamp :status="$hotel->price_status" />
            @endif
        </div>
    </div>
</article>

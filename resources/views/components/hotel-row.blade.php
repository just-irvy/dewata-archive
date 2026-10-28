@props(['hotel'])

@php($hero = $hotel->heroMedia())

<article class="grid gap-4 border-t-2 border-ink py-5 sm:grid-cols-[9rem_1fr_auto] sm:items-start">
    @if ($hero)
        <a href="{{ route('hotels.show', $hotel->slug) }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $hero->url() }}" alt="" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="aspect-[4/3] w-full object-cover sm:w-36" loading="lazy">
        </a>
    @else
        <span class="hidden sm:block"></span>
    @endif

    <div>
        <p class="typewriter">{{ $hotel->area_label ? $hotel->area_label.', ' : '' }}{{ $hotel->region?->name }}, {{ $hotel->type->label() }}</p>
        <h3 class="mt-1 font-display text-xl font-bold leading-snug"><a href="{{ route('hotels.show', $hotel->slug) }}" class="hover:text-rust">{{ $hotel->name }}</a></h3>
        <p class="mt-2 line-clamp-2 text-[0.9375rem] text-ink-soft">{{ $hotel->summary }}</p>
    </div>

    <x-hotel-price :hotel="$hotel" class="sm:max-w-60 sm:text-right" />
</article>

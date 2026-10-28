@props(['place', 'saved' => false, 'headingLevel' => 'h3'])

@php($hero = $place->heroMedia())

<article id="map-item-{{ $place->id }}" tabindex="-1" class="flex h-full flex-col border-t-2 border-ink pt-3 outline-none focus-visible:outline-2 focus-visible:outline-offset-4">
    @if ($hero)
        <a href="{{ route('places.show', $place->slug) }}" tabindex="-1" aria-hidden="true" class="block">
            <img src="{{ $hero->url() }}" alt="" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="aspect-[4/3] w-full object-cover" loading="lazy">
        </a>
    @endif

    <p class="typewriter mt-3">{{ $place->area_label ? $place->area_label.', ' : '' }}{{ $place->region?->name }}</p>
    <{{ $headingLevel }} class="mt-1 font-display text-xl font-bold leading-snug">
        <a href="{{ route('places.show', $place->slug) }}" class="hover:text-rust">{{ $place->name }}</a>
    </{{ $headingLevel }}>
    <p class="mt-2 line-clamp-3 text-[0.9375rem] text-ink-soft">{{ $place->summary }}</p>

    <div class="mt-auto flex items-center justify-between gap-3 pt-4">
        <span class="text-sm text-ink-soft">{{ $place->categories->take(2)->pluck('name')->join(', ') }}</span>
        <x-bookmark-button :place="$place" :saved="$saved" />
    </div>
</article>

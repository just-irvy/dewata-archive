{{--
| Korsel "Pilihan Editor": kartu tengah tajam dan besar, tetangganya mengecil, buram, dan pudar; panah di
| kedua sisi dan titik di bawah. Tanpa putar otomatis. Tanpa JavaScript, semua kartu tetap terbaca
| berurutan karena tiap kartu adalah tautan biasa.
--}}
@props(['picks'])

<section aria-labelledby="pilihan-editor" class="mt-16 lg:mt-20" data-reveal>
    <h2 id="pilihan-editor" class="kicker text-center text-ink-soft">Pilihan editor</h2>

    <div x-data="editorPicks({{ $picks->count() }})" @keydown.left.prevent="prev()" @keydown.right.prevent="next()" tabindex="0"
         aria-roledescription="korsel" aria-label="Pilihan editor" class="picks mt-8 outline-none focus-visible:outline-2">
        <div class="picks__stage" @pointerdown="pointerStart($event)" @pointerup="pointerEnd($event)" @pointercancel="startX = null">
            @foreach ($picks as $i => $place)
                @php($photo = $place->heroMedia())
                <a href="{{ route('places.show', $place->slug) }}" :style="styleFor({{ $i }})" :aria-hidden="isActive({{ $i }}) ? 'false' : 'true'" :tabindex="isActive({{ $i }}) ? 0 : -1"
                   class="picks__card archive-frame bg-surface" aria-roledescription="kartu" aria-label="{{ $i + 1 }} dari {{ $picks->count() }}: {{ $place->name }}">
                    @if ($photo)
                        <img src="{{ $photo->url() }}" alt="{{ $photo->alt_text }}" @if ($photo->width) width="{{ $photo->width }}" height="{{ $photo->height }}" @endif class="picks__photo aspect-[16/9] w-full object-cover" loading="lazy" draggable="false">
                    @else
                        <div aria-hidden="true" class="flex aspect-[16/9] w-full items-center justify-center bg-ink text-paper"><span class="typewriter text-sm">Belum ada foto</span></div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                        <span class="truncate font-display text-base font-bold leading-snug">{{ $place->name }}</span>
                        <span class="typewriter shrink-0 text-[0.6875rem] uppercase tracking-[0.1em] text-ink-soft">{{ $place->region?->name }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-center gap-4">
            <button type="button" @click="prev()" class="flex size-11 items-center justify-center rounded-full border border-ink/35 bg-surface hover:bg-ink hover:text-paper" aria-label="Sorotan sebelumnya">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
            <ul class="flex items-center gap-2" aria-label="Pilih sorotan">
                @foreach ($picks as $i => $place)
                    <li><button type="button" @click="goTo({{ $i }})" :aria-current="isActive({{ $i }}) ? 'true' : 'false'" :class="isActive({{ $i }}) ? 'bg-rust' : 'bg-ink/25 hover:bg-ink/50'" class="block size-2.5 rounded-full p-0 transition-colors" aria-label="Sorotan {{ $i + 1 }}: {{ $place->name }}"></button></li>
                @endforeach
            </ul>
            <button type="button" @click="next()" class="flex size-11 items-center justify-center rounded-full border border-ink/35 bg-surface hover:bg-ink hover:text-paper" aria-label="Sorotan berikutnya">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
            </button>
        </div>
    </div>
</section>

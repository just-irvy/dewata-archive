@php($hero = $hotel->heroMedia())

<x-layouts.app :title="$hotel->name" :description="$hotel->summary">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('hotels.index') }}">Penginapan</a></nav>

    <article class="mt-3 grid gap-10 md:grid-cols-12">
        <div class="md:col-span-8">
            <p class="typewriter">{{ $hotel->area_label ? $hotel->area_label.', ' : '' }}{{ $hotel->region?->name }}, {{ $hotel->type->label() }}</p>
            <h1 class="mt-2 text-4xl sm:text-5xl">{{ $hotel->name }}</h1>
            <p class="mt-5 max-w-[62ch] text-xl leading-relaxed text-ink-soft">{{ $hotel->summary }}</p>

            @if ($hero)
                <figure class="mt-8">
                    <img src="{{ $hero->url() }}" alt="{{ $hero->alt_text }}" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="h-auto w-full">
                    @if ($hero->caption || $hero->credit)
                        <figcaption class="typewriter mt-2">{{ $hero->caption }}@if ($hero->credit) Foto: {{ $hero->credit }}@endif</figcaption>
                    @endif
                </figure>
            @endif

            @if (\App\Support\MapMarkers::enabled())
                <section aria-labelledby="lokasi" class="mt-10">
                    <h2 id="lokasi" class="text-2xl">Lokasi</h2>
                    <x-map :markers="\App\Support\MapMarkers::forHotel($hotel)" :label="'Peta lokasi '.$hotel->name" class="mt-4" />
                </section>
            @endif
        </div>

        <aside class="md:col-span-4" aria-labelledby="harga">
            <div class="border-t-2 border-ink pt-4">
                <h2 id="harga" class="text-xl">Harga</h2>
                <x-hotel-price :hotel="$hotel" class="mt-3" />
                <p class="mt-4 text-sm text-ink-soft">Ketersediaan kamar tidak ditampilkan. Tanyakan langsung ke penginapan untuk tanggal yang Anda inginkan.</p>
            </div>

            <dl class="mt-8 space-y-4 text-[0.9375rem]">
                @if ($hotel->address)
                    <div><dt class="font-semibold">Alamat</dt><dd>{{ $hotel->address }}</dd></div>
                @endif
                <div>
                    <dt class="font-semibold">Peta</dt>
                    <dd><a class="btn btn-link" rel="noopener noreferrer" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ $hotel->lat }},{{ $hotel->lng }}">Buka di Google Maps</a></dd>
                </div>
            </dl>

            <div class="mt-8 space-y-2 border-t border-brass/60 pt-4">
                <x-verification :status="\App\Enums\VerificationStatus::Verified" :at="$hotel->verified_at" label="Data hotel" />
                @if ($hotel->source_url)
                    <p class="text-sm"><a class="btn btn-link" rel="noopener noreferrer nofollow" href="{{ $hotel->source_url }}">Lihat sumber</a></p>
                @endif
            </div>
        </aside>
    </article>
</x-layouts.app>

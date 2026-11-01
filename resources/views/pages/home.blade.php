@php($heroImage = $featured?->heroMedia())

<x-layouts.app :description="__('ui.home.description')">
    <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-14">
        <section class="lg:col-span-7">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl">{{ __('ui.home.title') }}</h1>
            <p class="mt-6 max-w-[56ch] text-lg leading-relaxed text-ink-soft">{{ __('ui.home.lead') }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-4">
                <x-button :href="route('places.index')">{{ __('ui.home.cta_explore') }}</x-button>
                @if (config('archive.features.planner'))
                    <a href="{{ route('planner.index') }}" class="btn btn-link">{{ __('ui.home.cta_planner') }}</a>
                @endif
            </div>
        </section>

        {{-- Foto sampul Pulau Bali (Admin, Beranda). Tanpa foto lengkap: lembar peta Bali. --}}
        <div class="lg:col-span-5">
            @if ($cover)
                <figure>
                    <img src="{{ $cover['url'] }}" alt="{{ $cover['alt_text'] }}" @if ($cover['width'] ?? null) width="{{ $cover['width'] }}" height="{{ $cover['height'] }}" @endif class="aspect-[4/3] w-full object-cover sm:aspect-[16/10] lg:aspect-[4/5]" fetchpriority="high">
                    <figcaption class="typewriter mt-2 flex flex-wrap justify-between gap-x-4 gap-y-1">
                        @if ($cover['caption'] ?? null)<span>{{ $cover['caption'] }}</span>@endif
                        <span>Foto: {{ collect([$cover['credit'] ?? null, $cover['license_note'] ?? null])->filter()->join(', ') }}</span>
                    </figcaption>
                </figure>
            @else
                <x-bali-plate :points="$mapPoints" :total="$placeTotal" />
                @can('manage-site')
                    <p class="mt-2 text-sm"><a class="btn btn-link px-0" href="{{ route('admin.home.edit') }}">Pasang foto sampul Pulau Bali</a></p>
                @endcan
            @endif
        </div>
    </div>

    @if ($featured)
        <section aria-labelledby="sorotan" class="mt-20 grid gap-8 border-t-[3px] border-double border-ink pt-8 lg:grid-cols-12">
            <div @class(['lg:col-span-7' => $heroImage, 'hidden' => ! $heroImage])>
                @if ($heroImage)
                    <figure>
                        <a href="{{ route('places.show', $featured->slug) }}" tabindex="-1" aria-hidden="true">
                            <img src="{{ $heroImage->url() }}" alt="" @if ($heroImage->width) width="{{ $heroImage->width }}" height="{{ $heroImage->height }}" @endif class="aspect-[3/2] w-full object-cover" loading="lazy">
                        </a>
                        @if ($heroImage->credit)
                            <figcaption class="typewriter mt-2">Foto: {{ $heroImage->credit }}</figcaption>
                        @endif
                    </figure>
                @endif
            </div>
            <div @class(['lg:col-span-5' => $heroImage, 'lg:col-span-8' => ! $heroImage])>
                <p class="typewriter">Berkas pilihan, {{ $featured->region?->name }}</p>
                <h2 id="sorotan" class="mt-2 font-display text-4xl font-bold leading-tight"><a class="hover:text-rust" href="{{ route('places.show', $featured->slug) }}">{{ $featured->name }}</a></h2>
                <p class="mt-4 max-w-[52ch] text-lg leading-relaxed text-ink-soft">{{ $featured->summary }}</p>
                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <x-button :href="route('places.show', $featured->slug)" variant="secondary">Buka berkas</x-button>
                    <x-bookmark-button :place="$featured" :saved="in_array($featured->id, $saved, true)" />
                </div>
            </div>
        </section>
    @endif

    @if ($isEmpty)
        <section class="mt-16 max-w-xl border-t border-brass/60 pt-6">
            <h2 class="text-2xl">Arsip sedang disusun</h2>
            <p class="mt-2 text-ink-soft">Belum ada tempat yang terbit. Setiap tempat baru muncul di sini setelah sumber dan tanggal verifikasinya diperiksa.</p>
        </section>
    @else
        @if ($regions->isNotEmpty())
            <section aria-labelledby="wilayah" class="mt-20 border-t border-brass/60 pt-6">
                <h2 id="wilayah" class="text-2xl">Telusuri menurut wilayah</h2>
                <ul class="mt-6 columns-2 gap-x-10 sm:columns-3 lg:columns-4">
                    @foreach ($regions as $region)
                        <li class="break-inside-avoid py-1.5">
                            <a class="btn btn-link block" href="{{ route('regions.show', $region->slug) }}">{{ $region->name }}</a>
                            <span class="typewriter pl-1">{{ $region->places_count }} tempat</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($places->isNotEmpty())
            <section aria-labelledby="terbaru" class="mt-20 border-t border-brass/60 pt-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="terbaru" class="text-2xl">Catatan terbaru</h2>
                    <a class="btn btn-link" href="{{ route('places.index') }}">Lihat semua tempat</a>
                </div>
                <ul class="mt-8 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($places as $place)
                        <li><x-place-card :place="$place" :saved="in_array($place->id, $saved, true)" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif

    @if ($hotels->isNotEmpty())
        <section aria-labelledby="penginapan" class="mt-20 grid gap-10 border-t border-brass/60 pt-6 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <h2 id="penginapan" class="text-2xl">Penginapan</h2>
                <p class="mt-3 text-ink-soft">Harga adalah kisaran per kamar per malam, lengkap dengan jenis sumber dan tanggal periksanya. Situs ini tidak menampilkan ketersediaan kamar.</p>

                <form method="GET" action="{{ route('hotels.index') }}" class="mt-6 space-y-4" x-data="{ busy: false }" x-on:submit="busy = true">
                    <x-select name="region" label="Wilayah" :options="$hotelRegions->pluck('name', 'slug')->all()" placeholder="Semua wilayah" />
                    <x-field name="max" label="Harga maksimal per malam (Rp)" type="number" min="0" inputmode="numeric" />
                    <x-button type="submit" variant="secondary">Cari penginapan</x-button>
                </form>
            </div>

            <div class="lg:col-span-8">
                @foreach ($hotels as $hotel)
                    <x-hotel-row :hotel="$hotel" />
                @endforeach
                <p class="border-t-2 border-ink pt-4"><a class="btn btn-link" href="{{ route('hotels.index') }}">Lihat semua penginapan</a></p>
            </div>
        </section>
    @endif

    @if (config('archive.features.planner'))
        <section aria-labelledby="rencana" class="mt-20 border-y-[3px] border-double border-ink py-10">
            <h2 id="rencana" class="text-3xl">Susun perjalanan Anda</h2>
            <p class="mt-3 max-w-[56ch] text-ink-soft">Rencana bisa dimulai tanpa akun. Buat akun bila ingin menyimpannya dan membukanya lagi di perangkat lain.</p>
            <div class="mt-6"><x-button :href="route('planner.index')">{{ __('ui.nav.planner') }}</x-button></div>
        </section>
    @endif
</x-layouts.app>

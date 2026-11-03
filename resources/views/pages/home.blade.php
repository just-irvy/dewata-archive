{{--
| Beranda, diadaptasi dari desain Stitch "The Dewata Archive".
| Yang dipertahankan: hero foto selebar layar dengan masthead di atasnya, bilah koleksi, berkas pilihan
| bergaya dosir, kartu berkas bercap verifikasi, kartu penginapan, ajakan perencana, footer berkolom.
| Yang diubah agar sesuai blueprint: satu berkas pilihan (bukan carousel, FILE 03), semua angka dan isi
| dari database, klaim "bebas algoritma" dihapus (planner punya mode rekomendasi berbasis aturan),
| tanpa ikon Material Symbols dan Tailwind CDN, warna memakai token proyek.
--}}
@php
    $heroImage = $featured?->heroMedia();
    // Lebar mengikuti desain Stitch: hampir selebar layar dengan margin 3,5rem di desktop.
    $wide = 'mx-auto w-full max-w-[120rem] px-5 sm:px-8 lg:px-14';
    $kicker = 'Arsip lapangan'.($stats['places'] > 0 ? ' · '.$stats['places'].' berkas terbit' : '');
    $statLine = number_format($stats['places'], 0, ',', '.').' tempat · '.number_format($stats['hotels'], 0, ',', '.').' penginapan · '.$stats['regions'].' wilayah';
@endphp

<x-layouts.app :description="__('ui.home.description')" :bleed="true" :masthead="$cover ? 'none' : 'paper'">

    {{-- 1. HERO --}}
    @if ($cover)
        {{-- Hero satu layar penuh: foto, masthead, dan isi pas di tinggi layar (svh agar tidak tertutup bilah alamat ponsel). --}}
        {{-- hero-cover: saat digulir, foto perlahan mendekat dan teks pembuka memudar (lihat app.css, efek gulir). --}}
        <section aria-labelledby="judul-beranda" class="hero-cover relative isolate flex min-h-svh flex-col overflow-hidden bg-ink text-surface">
            <img src="{{ $cover['url'] }}" alt="{{ $cover['alt_text'] }}" @if ($cover['width'] ?? null) width="{{ $cover['width'] }}" height="{{ $cover['height'] }}" @endif class="hero-cover__photo absolute inset-0 -z-20 h-full w-full object-cover" fetchpriority="high">
            {{-- Gradasi agar teks masthead dan judul selalu terbaca di atas foto apa pun. --}}
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-b from-[#101713]/80 via-[#161d19]/40 to-[#161d19]/60"></div>
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-r from-[#101713]/55 via-transparent to-transparent"></div>

            <x-masthead variant="overlay" />

            <div class="hero-cover__lead {{ $wide }} flex flex-1 items-center py-5 sm:py-6 lg:py-[2vh]">
                <div class="max-w-3xl">
                    <p class="kicker flex items-center gap-2 text-[#ffdad2]"><span aria-hidden="true" class="inline-block size-2.5 bg-[#ffdad2]"></span>{{ $kicker }}</p>
                    <h1 id="judul-beranda" class="mt-3 text-[2.1rem] leading-[1.08] text-surface drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)] sm:mt-4 sm:text-5xl lg:mt-[1.8vh] lg:text-[clamp(2.4rem,6.6vh,4.25rem)]">Panduan penjelajahan Bali sebagai arsip pribadi yang tenang</h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-surface/90 sm:mt-5 sm:text-lg drop-shadow-[0_1px_4px_rgba(0,0,0,0.6)] lg:mt-[2.2vh] lg:text-[clamp(1rem,2.15vh,1.25rem)]">Bukan panduan wisata komersial, melainkan catatan lapangan bersumber untuk pejalan yang menghargai tempo perlahan, lanskap budaya, dan keaslian tempat. Tanpa iklan dan tanpa perantara transaksi.</p>
                    <div class="mt-5 flex flex-wrap items-center gap-3 sm:mt-7 lg:mt-[3vh]">
                        <x-button :href="route('places.index')">{{ __('ui.home.cta_explore') }}</x-button>
                        @if (config('archive.features.planner'))
                            <a href="{{ route('planner.index') }}" class="kicker inline-flex min-h-11 items-center gap-2 border border-white/35 bg-white/10 px-5 text-surface backdrop-blur-sm hover:bg-white/20">{{ __('ui.home.cta_planner') }} <span aria-hidden="true" class="text-[#ffb4a3]">→</span></a>
                        @endif
                    </div>
                    @if ($stats['places'] > 0)
                        <p class="typewriter mt-4 inline-flex flex-wrap gap-x-2 border border-white/25 sm:mt-6 bg-white/10 px-3 py-2 text-surface/90 backdrop-blur-sm lg:mt-[2.6vh]">
                            <span class="font-bold uppercase text-surface">Koleksi terbit:</span> {{ $statLine }}
                        </p>
                    @endif
                </div>
            </div>

            {{--
            | Kaki hero. Foto memudar ke warna kertas, lalu keterangan foto tampil di atas kertas
            | seperti keterangan foto di surat kabar. Peralihan ke isi halaman jadi lembut, tidak terpotong.
            --}}
            <div aria-hidden="true" class="pointer-events-none h-16 bg-gradient-to-b from-transparent via-paper/60 to-paper sm:h-24"></div>
            <div class="bg-paper text-ink">
                <div class="{{ $wide }} flex flex-wrap items-baseline justify-between gap-x-8 gap-y-1 border-b border-brass/50 pb-3">
                    <a href="#isi-beranda" class="kicker inline-flex shrink-0 items-center gap-2 text-rust hover:text-ink">
                        Gulir ke arsip <span aria-hidden="true" class="motion-safe:animate-bounce">↓</span>
                    </a>
                    <p class="typewriter flex min-w-0 max-w-full items-baseline justify-end gap-2 text-[0.75rem] sm:max-w-[80ch]">
                        @if ($cover['caption'] ?? null)
                            {{-- Keterangan panjang dipotong satu baris; teks lengkap ada di title dan alt foto. --}}
                            <span class="min-w-0 truncate" title="{{ $cover['caption'] }}">{{ $cover['caption'] }}</span>
                            <span aria-hidden="true">·</span>
                        @endif
                        <span class="shrink-0 font-bold text-ink">Foto: {{ collect([$cover['credit'] ?? null, $cover['license_note'] ?? null])->filter()->join(', ') }}</span>
                    </p>
                </div>
            </div>
        </section>
    @else
        {{-- Tanpa foto sampul: hero di atas kertas dengan lembar peta Bali. --}}
        <section aria-labelledby="judul-beranda" class="{{ $wide }} grid gap-10 py-12 lg:grid-cols-12 lg:items-center lg:gap-14 lg:py-16">
            <div class="lg:col-span-7">
                <p class="kicker flex items-center gap-2 text-rust"><span aria-hidden="true" class="inline-block size-2.5 bg-rust"></span>{{ $kicker }}</p>
                <h1 id="judul-beranda" class="mt-4 text-4xl leading-[1.1] sm:text-5xl lg:text-[3.4rem]">Panduan penjelajahan Bali sebagai arsip pribadi yang tenang</h1>
                <p class="mt-5 max-w-[60ch] text-lg leading-relaxed text-ink-soft">Bukan panduan wisata komersial, melainkan catatan lapangan bersumber untuk pejalan yang menghargai tempo perlahan, lanskap budaya, dan keaslian tempat. Tanpa iklan dan tanpa perantara transaksi.</p>
                <div class="mt-7 flex flex-wrap items-center gap-4">
                    <x-button :href="route('places.index')">{{ __('ui.home.cta_explore') }}</x-button>
                    @if (config('archive.features.planner'))
                        <x-button :href="route('planner.index')" variant="secondary">{{ __('ui.home.cta_planner') }} <span aria-hidden="true">→</span></x-button>
                    @endif
                </div>
                @if ($stats['places'] > 0)
                    <p class="typewriter mt-6 inline-flex flex-wrap gap-x-2 border border-ink/25 bg-surface px-3 py-2">
                        <span class="font-bold uppercase text-ink">Koleksi terbit:</span> {{ $statLine }}
                    </p>
                @endif
            </div>
            <div class="lg:col-span-5">
                <x-bali-plate :points="$mapPoints" :total="$placeTotal" />
                @can('manage-site')
                    <p class="mt-2 text-sm"><a class="btn btn-link px-0" href="{{ route('admin.home.edit') }}">Pasang foto sampul Pulau Bali</a></p>
                @endcan
            </div>
        </section>
    @endif

    <div id="isi-beranda" class="{{ $wide }} scroll-mt-4">

        {{-- 2. BERKAS PILIHAN: satu cerita, bukan carousel (FILE 03). --}}
        @if ($featured)
            @php
                $coords = \App\Support\ArchiveCode::dms($featured->lat, $featured->lng);
                $access = $featured->accessibility_status === \App\Enums\VerificationStatus::Verified
                    ? collect((array) $featured->accessibility)->filter(fn ($v) => $v === 'yes')->keys()->map(fn ($k) => config("archive.accessibility_attributes.$k"))->filter()->join(', ')
                    : null;
            @endphp
            <section aria-labelledby="sorotan" class="mt-16 lg:mt-20">
                {{-- featured-file: saat ditunjuk kursor atau difokus keyboard, berkas terangkat dari tempatnya (lihat app.css). --}}
                <div class="featured-file">
                <div class="featured-file__sheet archive-frame p-5 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/20 pb-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="stamp stamp-solid">Pilihan editor</span>
                            <span class="kicker text-ink">Berkas utama {{ \App\Support\ArchiveCode::for($featured) }}</span>
                        </div>
                        <x-stamp :status="$featured->verification_status" />
                    </div>

                    <div @class(['mt-6 grid gap-8', 'lg:grid-cols-12' => $heroImage])>
                        @if ($heroImage)
                            <figure class="lg:col-span-6">
                                <a href="{{ route('places.show', $featured->slug) }}" tabindex="-1" aria-hidden="true" class="block border border-ink/15 bg-paper p-2">
                                    <span class="featured-file__window block overflow-hidden">
                                        <img src="{{ $heroImage->url() }}" alt="" @if ($heroImage->width) width="{{ $heroImage->width }}" height="{{ $heroImage->height }}" @endif class="featured-file__photo aspect-[4/3] w-full object-cover" loading="lazy">
                                    </span>
                                </a>
                                @if ($heroImage->credit)
                                    <figcaption class="typewriter mt-2 flex flex-wrap justify-between gap-x-3 text-[0.75rem]"><span>Foto: {{ $heroImage->credit }}</span>@if ($heroImage->license_note)<span class="font-bold text-rust">({{ $heroImage->license_note }})</span>@endif</figcaption>
                                @endif
                            </figure>
                        @endif

                        <div @class(['flex flex-col gap-5', 'lg:col-span-6' => $heroImage, 'max-w-4xl' => ! $heroImage])>
                            <p class="kicker text-brass-deep">{{ collect([$featured->area_label, $featured->region?->name])->filter()->unique()->join(' · ') }}</p>
                            <h2 id="sorotan" class="-mt-2 text-3xl leading-tight sm:text-[2.1rem]"><a class="hover:text-rust" href="{{ route('places.show', $featured->slug) }}">{{ $featured->name }}</a></h2>
                            <p class="max-w-[68ch] text-[1.0625rem] leading-relaxed text-ink-soft">{{ $featured->summary }}</p>

                            <dl class="typewriter grid gap-2 border border-ink/20 bg-paper p-4 text-[0.8125rem]">
                                <div class="flex flex-wrap justify-between gap-x-4"><dt class="uppercase">Titik koordinat</dt><dd class="font-bold text-ink">{{ $coords ?? 'Belum dicatat' }}</dd></div>
                                <div class="flex flex-wrap justify-between gap-x-4"><dt class="uppercase">Aksesibilitas</dt><dd class="text-ink">{{ $access ?: 'Belum diverifikasi' }}</dd></div>
                                <div class="flex flex-wrap justify-between gap-x-4"><dt class="uppercase">Jam buka</dt><dd class="text-ink">{{ $featured->opening_hours_status === \App\Enums\VerificationStatus::Verified ? 'Terverifikasi, lihat berkas' : 'Cek sebelum berkunjung' }}</dd></div>
                                @if ($featured->verified_at)
                                    <div class="flex flex-wrap justify-between gap-x-4"><dt class="uppercase">Diperiksa</dt><dd class="text-ink">{{ $featured->verified_at->translatedFormat('j F Y') }}</dd></div>
                                @endif
                            </dl>

                            <div class="mt-auto flex flex-wrap items-center justify-between gap-4 border-t border-ink/20 pt-4">
                                <a class="kicker text-[0.8125rem] text-rust hover:text-ink" href="{{ route('places.show', $featured->slug) }}">Buka berkas lengkap <span class="featured-file__arrow" aria-hidden="true">→</span></a>
                                <x-bookmark-button :place="$featured" :saved="in_array($featured->id, $saved, true)" />
                            </div>
                        </div>
                    </div>
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
            {{-- 3. WILAYAH: indeks bergaya buku besar. --}}
            @if ($regions->isNotEmpty())
                <section aria-labelledby="wilayah" class="mt-16 lg:mt-20">
                    <p class="kicker text-rust">[Indeks wilayah]</p>
                    <h2 id="wilayah" class="mt-1 text-3xl">Telusuri menurut wilayah</h2>
                    <ul class="mt-6 grid border-l border-t border-ink/25 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                        @foreach ($regions as $region)
                            <li class="border-b border-r border-ink/25">
                                <a href="{{ route('regions.show', $region->slug) }}" class="group flex min-h-16 items-center justify-between gap-4 px-4 py-3 hover:bg-surface">
                                    <span class="flex items-baseline gap-3">
                                        <span class="kicker w-9 text-ink-soft">{{ \App\Support\ArchiveCode::region($region) }}</span>
                                        <span class="font-display text-lg font-bold group-hover:text-rust">{{ $region->name }}</span>
                                    </span>
                                    <span class="typewriter shrink-0">{{ $region->places_count }} tempat</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- 4. TEMPAT TERBARU --}}
            @if ($places->isNotEmpty())
                <section aria-labelledby="terbaru" class="mt-16 lg:mt-20">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="kicker text-rust">[Catatan terakhir]</p>
                            <h2 id="terbaru" class="mt-1 text-3xl">Tempat terbaru yang masuk arsip</h2>
                        </div>
                        <a class="kicker text-rust hover:text-ink" href="{{ route('places.index') }}">Lihat seluruh {{ $stats['places'] }} entri <span aria-hidden="true">→</span></a>
                    </div>
                    <ul class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($places as $place)
                            <li><x-dossier-card :place="$place" :saved="in_array($place->id, $saved, true)" /></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif

        {{-- 5. PENGINAPAN: kartu terpisah, tidak pernah dijumlahkan ke biaya perjalanan. --}}
        @if ($hotels->isNotEmpty())
            <section aria-labelledby="penginapan" class="mt-16 lg:mt-20">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="kicker text-brass-deep">[Penginapan · non-komersial]</p>
                        <h2 id="penginapan" class="mt-1 text-3xl">Cuplikan penginapan</h2>
                    </div>
                    <p class="kicker bg-surface px-3 py-1.5 text-ink-soft">Arsip ini tidak menerima pemesanan atau komisi</p>
                </div>
                <div class="mt-8 grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($hotels as $hotel)
                        <x-hotel-dossier :hotel="$hotel" />
                    @endforeach
                </div>
                <p class="mt-6"><a class="kicker text-rust hover:text-ink" href="{{ route('hotels.index') }}">Lihat dan saring semua penginapan <span aria-hidden="true">→</span></a></p>
            </section>
        @endif

        {{-- 6. AJAKAN PERENCANA --}}
        @if (config('archive.features.planner'))
            <section aria-labelledby="rencana" class="archive-slip relative mb-16 mt-16 overflow-hidden p-6 sm:p-10 lg:mb-20 lg:mt-20">
                <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-4 hidden select-none font-display text-[6.5rem] font-black uppercase leading-none text-ink/[0.06] sm:block">Itinera</span>
                <div class="relative max-w-3xl">
                    <p class="kicker flex flex-wrap items-center gap-3"><span class="stamp stamp-solid">Modul perjalanan</span><span class="text-ink">Dua cara memulai</span></p>
                    <h2 id="rencana" class="mt-4 text-3xl leading-tight sm:text-4xl">Susun perjalanan dengan tempo Anda sendiri</h2>
                    <p class="mt-4 text-lg leading-relaxed text-ink-soft">Jawab enam pertanyaan singkat untuk mendapat draf per hari beserta alasan setiap pilihan, atau mulai dari arsip kosong dan pilih sendiri setiap tempat. Keduanya bisa dicoba tanpa akun, dan semua isinya tetap bisa Anda ubah.</p>
                    <div class="mt-6"><x-button :href="route('planner.index')">{{ __('ui.nav.planner') }} <span aria-hidden="true">→</span></x-button></div>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>

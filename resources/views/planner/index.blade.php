@php
    // Urutan dan nama langkah mengikuti wizard di planner/recommend.blade.php.
    $steps = [
        ['Kerangka perjalanan', 'Jumlah hari, tanggal mulai, titik awal'],
        ['Minat', 'Tema utama, paling banyak '.config('planner.max_themes')],
        ['Wilayah', 'Wilayah tujuan dan jarak per hari'],
        ['Tempo dan batasan', 'Tempo, transportasi, waktu harian'],
        ['Penginapan', 'Opsional, kisaran harga per malam'],
        ['Tinjau', 'Periksa isian dan beri judul arsip'],
    ];

    // Pratinjau Susun sendiri: dua tempat sudah di Hari 1, dua lagi bisa ditambahkan.
    $showDemo = $demoPlaces->count() >= 4;
    $demoDay1 = $demoPlaces->take(2)->pluck('name')->values();
    $demoPool = $demoPlaces->slice(2)->map(fn ($place) => [
        'name' => $place->name,
        'meta' => collect([$place->region?->name, $place->area_label])->filter()->unique()->implode(', '),
    ])->values();
@endphp

<x-layouts.app title="Rencanakan perjalanan" description="Susun arsip perjalanan Bali dari rekomendasi atau pilih sendiri setiap tempat. Bisa dimulai tanpa akun.">
    <div class="flex flex-wrap items-end gap-x-14 gap-y-8">
        <header class="min-w-0 flex-[2_1_30rem]">
            <h1 class="text-4xl sm:text-5xl">Rencanakan perjalanan</h1>
            <p class="mt-4 max-w-[58ch] text-lg leading-relaxed text-ink-soft">
                Jawab enam pertanyaan dan arsip menyusun draf per hari. Semua isinya tetap bisa Anda ubah di editor. Tidak perlu akun untuk mencoba.
            </p>
        </header>

        @if ($recent->isNotEmpty())
            <aside aria-labelledby="arsip-terakhir" class="min-w-0 flex-[1_1_18rem] border-t border-ink pt-3">
                <h2 id="arsip-terakhir" class="typewriter font-normal">{{ auth()->check() ? 'Arsip terakhir Anda' : 'Draf di browser ini' }}</h2>
                <ul class="mt-2 space-y-2">
                    @foreach ($recent as $plan)
                        <li>
                            <a class="font-semibold underline-offset-4 hover:text-rust hover:underline" href="{{ \App\Planner\PlanAccess::editorUrl($plan) }}">{{ $plan->title }}</a>
                            <p class="typewriter">{{ $plan->days_count }} hari, diubah {{ $plan->updated_at->translatedFormat('j F Y') }}</p>
                        </li>
                    @endforeach
                </ul>
                @auth
                    <p class="mt-2"><a class="btn btn-link" href="{{ route('account.plans.index') }}">Semua arsip</a></p>
                @endauth
            </aside>
        @endif
    </div>

    {{-- Jalur utama: rekomendasi. --}}
    <section aria-labelledby="mode-rekomendasi" class="mt-12 grid gap-10 border border-ink bg-surface p-6 sm:p-10 lg:grid-cols-2 lg:gap-16 lg:p-12">
        <div class="flex flex-col">
            <h2 id="mode-rekomendasi" class="text-3xl sm:text-4xl">Dapatkan rekomendasi</h2>
            <p class="mt-4 max-w-[46ch] text-lg leading-relaxed text-ink-soft">Arsip memilih dari tempat yang sudah diverifikasi dan menulis alasan setiap pilihan. Anda bisa kembali ke pertanyaan mana pun sebelum draf dibuat.</p>
            <p class="typewriter mt-4">Contoh: tiga hari, sekitar Gianyar, tempo santai.</p>
            <div class="mt-auto pt-8"><x-button :href="route('planner.recommend')" class="min-h-[52px] px-7 text-[1.0625rem]">Mulai 6 pertanyaan</x-button></div>
        </div>

        <div class="border-t-[3px] border-double border-ink pt-1.5">
            <ol aria-label="Isi enam pertanyaan" class="divide-y divide-ink/15">
                @foreach ($steps as $i => [$title, $detail])
                    <li class="flex gap-3.5 py-3">
                        <span class="typewriter w-5 flex-none pt-0.5 text-[0.9375rem] text-rust">{{ $i + 1 }}</span>
                        <span>
                            <span class="block font-semibold">{{ $title }}</span>
                            <span class="block text-sm text-ink-soft">{{ $detail }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Jalur kedua: susun sendiri, dengan pratinjau editor yang bisa dicoba. --}}
    <section aria-labelledby="mode-manual" class="mt-12 grid gap-10 border-y-[3px] border-double border-ink py-10 lg:gap-16 {{ $showDemo ? 'lg:grid-cols-2' : '' }}">
        <div class="flex flex-col">
            <p class="typewriter">Sudah punya daftar tempat?</p>
            <h2 id="mode-manual" class="mt-2 text-3xl sm:text-[2.125rem]">Susun sendiri</h2>
            <p class="mt-4 max-w-[44ch] text-lg leading-relaxed text-ink-soft">Mulai dari arsip kosong. Anda yang memilih setiap tempat. Tidak ada yang disisipkan otomatis.</p>

            <ul class="mt-5 space-y-3">
                <li class="flex items-center gap-3">
                    <svg aria-hidden="true" class="size-[22px] flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>
                    Cari tempat di katalog terbit
                </li>
                <li class="flex items-center gap-3">
                    <svg aria-hidden="true" class="size-[22px] flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="16"/><path d="M3.5 9h17M8 2.5v4M16 2.5v4M12 12.5v5M9.5 15h5"/></svg>
                    Masukkan ke hari yang Anda pilih
                </li>
                <li class="flex items-center gap-3">
                    <svg aria-hidden="true" class="size-[22px] flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M12 3l-3 3M12 3l3 3M12 21l-3-3M12 21l3-3"/></svg>
                    Atur urutan, rute di peta ikut berubah
                </li>
            </ul>

            <div class="mt-auto pt-8"><x-button variant="secondary" :href="route('planner.manual')" class="min-h-[52px] border-2 px-7 text-[1.0625rem]">Buka arsip kosong</x-button></div>
        </div>

        @if ($showDemo)
            <figure
                class="border border-ink bg-surface p-6"
                x-data="{
                    day1: @js($demoDay1),
                    pool: @js($demoPool),
                    has(name) { return this.day1.includes(name) },
                    add(name) { if (! this.has(name)) this.day1.push(name) },
                }"
            >
                <div aria-hidden="true" class="flex min-h-11 items-center gap-2.5 border-[1.5px] border-ink bg-white px-3.5 text-ink-soft">
                    <svg class="size-[18px] flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>
                    Cari tempat di katalog
                </div>

                <ul aria-label="Contoh hasil pencarian" class="mt-3 divide-y divide-ink/15 border-b border-ink/15">
                    <template x-for="place in pool" :key="place.name">
                        <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2.5">
                            <span>
                                <span class="block text-[0.9375rem] font-semibold" x-text="place.name"></span>
                                <span class="block text-[0.8125rem] text-ink-soft" x-text="place.meta"></span>
                            </span>
                            <button type="button" x-show="! has(place.name)" x-on:click="add(place.name)" class="min-h-10 bg-ink px-3.5 text-sm font-semibold text-paper hover:bg-rust">Tambah ke Hari 1</button>
                            <span x-show="has(place.name)" x-cloak class="typewriter text-moss">Sudah di Hari 1</span>
                        </li>
                    </template>
                </ul>

                <div class="mt-4 grid grid-cols-2 gap-3.5">
                    <div class="border-t-[3px] border-double border-ink pt-2.5">
                        <p class="font-display text-lg font-bold">Hari 1</p>
                        <ol class="mt-2 space-y-1.5" aria-live="polite">
                            <template x-for="(name, i) in day1" :key="name">
                                <li class="flex items-center gap-2.5 border border-ink/25 bg-paper px-2.5 py-2 text-sm">
                                    <span class="typewriter text-rust" x-text="i + 1"></span>
                                    <span class="min-w-0 flex-auto" x-text="name"></span>
                                    <svg aria-hidden="true" class="size-4 flex-none text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01"/></svg>
                                </li>
                            </template>
                        </ol>
                    </div>
                    <div class="border-t-[3px] border-double border-ink pt-2.5">
                        <p class="font-display text-lg font-bold">Hari 2</p>
                        <p class="mt-2 border-[1.5px] border-dashed border-ink/45 px-2.5 py-4 text-center text-sm text-ink-soft">Belum ada tempat</p>
                    </div>
                </div>

                <figcaption class="typewriter mt-4">Pratinjau. Tekan Tambah ke Hari 1 untuk mencoba. Tidak ada yang tersimpan.</figcaption>
            </figure>
        @endif
    </section>

    <p class="mt-10 max-w-[80ch] text-sm leading-relaxed text-ink-soft">
        Planner tidak menghitung biaya perjalanan. Harga hanya muncul sebagai kisaran per malam saat Anda mencari penginapan.
        Tanpa akun, draf tersimpan di browser ini selama {{ config('planner.guest_ttl_days') }} hari.
        Jarak dan waktu tempuh dari Google Maps adalah estimasi, bukan jaminan kondisi jalan.
    </p>
</x-layouts.app>

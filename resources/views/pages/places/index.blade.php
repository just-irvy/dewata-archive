@php($hasFilters = count($chips) > 0)

<x-layouts.app title="Jelajah" description="Jelajahi tempat di Bali menurut wilayah, kategori, tema, dan aksesibilitas.">
    <header class="max-w-2xl">
        <h1 class="text-4xl sm:text-5xl">Jelajahi arsip</h1>
        <p class="mt-3 text-ink-soft" role="status">
            {{ $places->total() }} tempat @if ($hasFilters) sesuai filter @else terbit @endif.
        </p>
    </header>

    <div class="mt-10 grid gap-10 lg:grid-cols-12" x-data="{ open: window.matchMedia('(min-width: 1024px)').matches }">
        <aside class="lg:col-span-3" aria-label="Filter">
            <button type="button" class="btn btn-secondary lg:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="filter-form">
                Saring tempat
            </button>

            <form id="filter-form" method="GET" action="{{ route('places.index') }}" class="mt-4 space-y-6 lg:mt-0" x-show="open" x-data="{ busy: false }" x-on:submit="busy = true">
                <div class="field">
                    <label for="q" class="field-label">Cari nama atau ringkasan</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="field-input">
                </div>

                @if ($regions->isNotEmpty())
                    <x-select name="region" label="Wilayah" :options="$regions->pluck('name', 'slug')->all()" :selected="$filters['region']" placeholder="Semua wilayah" />
                @endif
                @if ($categories->isNotEmpty())
                    <x-select name="category" label="Kategori" :options="$categories->pluck('name', 'slug')->all()" :selected="$filters['category']" placeholder="Semua kategori" />
                @endif
                @if ($themes->isNotEmpty())
                    <x-checks name="tags" legend="Tema" hint="Salah satu tema yang dipilih." :options="$themes->pluck('name', 'slug')->all()" :checked="$filters['tags']" />
                @endif
                @if ($access !== [])
                    <x-checks name="access" legend="Aksesibilitas" hint="Hanya dari data yang sudah diverifikasi." :options="$access" :checked="$filters['access']" />
                @endif

                <x-select name="sort" label="Urutkan" :options="['terbaru' => 'Terbaru', 'nama' => 'Nama A sampai Z']" :selected="$filters['sort']" />

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-primary" x-bind:aria-busy="busy.toString()">
                        <span x-text="busy ? 'Menerapkan' : 'Terapkan'">Terapkan</span>
                    </button>
                    @if ($hasFilters)
                        <a class="btn btn-link" href="{{ route('places.index') }}">Hapus semua filter</a>
                    @endif
                </div>
            </form>
        </aside>

        <section class="lg:col-span-9" aria-label="Hasil">
            @if ($hasFilters)
                <ul class="mb-8 flex flex-wrap gap-2" aria-label="Filter aktif">
                    @foreach ($chips as $chip)
                        <li><a class="btn btn-secondary min-h-9 px-3 text-sm" href="{{ $chip['url'] }}">{{ $chip['label'] }}<span class="sr-only">. Hapus filter ini</span></a></li>
                    @endforeach
                </ul>
            @endif

            @if ($places->isEmpty())
                <div class="max-w-lg border-t border-brass/60 pt-6">
                    @if ($hasFilters)
                        <h2 class="text-2xl">Tidak ada tempat yang cocok</h2>
                        <p class="mt-2 text-ink-soft">Coba longgarkan filter atau hapus kata kunci.</p>
                        <p class="mt-4"><a class="btn btn-secondary" href="{{ route('places.index') }}">Hapus semua filter</a></p>
                    @else
                        <h2 class="text-2xl">Belum ada tempat terbit</h2>
                        <p class="mt-2 text-ink-soft">Tempat muncul di sini setelah sumber dan tanggal verifikasinya diperiksa.</p>
                    @endif
                </div>
            @else
                <ul class="grid gap-x-8 gap-y-12 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($places as $place)
                        <li><x-place-card :place="$place" :saved="in_array($place->id, $saved, true)" /></li>
                    @endforeach
                </ul>
                <div class="mt-12">{{ $places->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>

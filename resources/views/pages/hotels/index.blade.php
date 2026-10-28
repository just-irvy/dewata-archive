@php($active = collect($filters)->except('sort')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())

<x-layouts.app title="Hotel" description="Akomodasi di Bali menurut wilayah dan kisaran harga per malam, dengan sumber dan tanggal verifikasi.">
    <header class="max-w-2xl">
        <h1 class="text-4xl sm:text-5xl">Penginapan</h1>
        <p class="mt-3 text-ink-soft">Harga adalah kisaran per kamar per malam beserta jenis sumber dan tanggal periksanya. Ketersediaan kamar tidak ditampilkan, dan situs ini tidak menerima pemesanan.</p>
    </header>

    <form method="GET" action="{{ route('hotels.index') }}" class="mt-10 grid gap-5 border-y border-ink/25 py-6 sm:grid-cols-2 lg:grid-cols-6 lg:items-end" x-data="{ busy: false }" x-on:submit="busy = true">
        <div class="lg:col-span-1"><x-select name="region" label="Wilayah" :options="$regions->pluck('name', 'slug')->all()" :selected="$filters['region']" placeholder="Semua" /></div>
        <div class="lg:col-span-1"><x-select name="type" label="Jenis" :options="$types->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" :selected="$filters['type']" placeholder="Semua" /></div>
        <div class="lg:col-span-1"><x-field name="min" label="Harga dari (Rp)" type="number" min="0" inputmode="numeric" :value="$filters['min']" /></div>
        <div class="lg:col-span-1"><x-field name="max" label="Sampai (Rp)" type="number" min="0" inputmode="numeric" :value="$filters['max']" /></div>
        <div class="lg:col-span-1"><x-select name="sort" label="Urutkan" :options="['terbaru' => 'Terbaru', 'nama' => 'Nama A sampai Z', 'harga' => 'Harga terendah']" :selected="$filters['sort']" /></div>
        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary" x-bind:aria-busy="busy.toString()"><span x-text="busy ? 'Menerapkan' : 'Terapkan'">Terapkan</span></button>
            @if ($active)
                <a class="btn btn-link" href="{{ route('hotels.index') }}">Hapus</a>
            @endif
        </div>
    </form>

    @if ($filters['min'] !== null || $filters['max'] !== null)
        <p class="typewriter mt-4">Filter harga mencocokkan kisaran harga. Hotel tanpa data harga tidak ikut dalam hasil.</p>
    @endif

    <p class="mt-6 text-sm text-ink-soft" role="status">{{ $hotels->total() }} penginapan{{ $active ? ' sesuai filter' : '' }}.</p>

    <section aria-label="Hasil" class="mt-2">
        @forelse ($hotels as $hotel)
            <x-hotel-row :hotel="$hotel" />
        @empty
            <div class="max-w-lg border-t border-brass/60 pt-6">
                @if ($active)
                    <h2 class="text-2xl">Tidak ada penginapan yang cocok</h2>
                    <p class="mt-2 text-ink-soft">Coba perlebar rentang harga atau pilih semua wilayah.</p>
                    <p class="mt-4"><a class="btn btn-secondary" href="{{ route('hotels.index') }}">Hapus filter</a></p>
                @else
                    <h2 class="text-2xl">Belum ada penginapan terbit</h2>
                    <p class="mt-2 text-ink-soft">Penginapan muncul setelah sumber dan tanggal verifikasinya diperiksa.</p>
                @endif
            </div>
        @endforelse
    </section>

    <div class="mt-10">{{ $hotels->links() }}</div>
</x-layouts.app>

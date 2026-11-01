<x-layouts.admin title="Hapus semua tempat dan hotel">
    <h1 class="text-4xl">Hapus semua tempat dan hotel</h1>
    <p class="mt-3 max-w-[62ch] text-ink-soft">Untuk mengganti katalog dari nol. Semua tempat dan hotel dihapus <strong class="text-oxblood">permanen</strong>, termasuk yang sudah terbit, beserta fotonya. Tindakan ini tidak bisa dibatalkan. Buat cadangan database lebih dulu bila datanya masih mungkin dibutuhkan.</p>

    <div class="mt-8 grid gap-10 lg:grid-cols-12">
        <section class="lg:col-span-6" aria-labelledby="dampak">
            <h2 id="dampak" class="text-2xl">Yang akan terhapus</h2>
            <dl class="mt-4 divide-y divide-ink/15 border-y-2 border-ink">
                @foreach ([
                    'Tempat' => $preview['places'],
                    'Hotel' => $preview['hotels'],
                    'Foto tempat dan hotel' => $preview['media'],
                    'Tempat di dalam rencana pengguna' => $preview['plan_items'],
                    'Bookmark pengguna' => $preview['saved_places'],
                    'Kaitan tempat di koleksi' => $preview['collection_links'],
                ] as $label => $count)
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt>{{ $label }}</dt>
                        <dd class="typewriter text-base font-bold">{{ number_format($count, 0, ',', '.') }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-sm text-ink-soft">Tetap ada: wilayah, kategori, tag, koleksi (menjadi kosong), akun, audit log, dan {{ $preview['plans_affected'] }} rencana pengguna yang terdampak (harinya tetap ada, tetapi kosong).</p>
        </section>

        <form method="POST" action="{{ route('admin.catalog-reset.destroy') }}" class="space-y-6 lg:col-span-6" x-data="{ busy: false }" x-on:submit="if (! confirm('Hapus permanen semua tempat dan hotel?')) { $event.preventDefault() } else { busy = true }">
            @csrf
            @method('DELETE')
            <h2 class="text-2xl">Konfirmasi</h2>
            <x-field name="confirmation" :label="'Ketik '.$phrase" :required="true" autocomplete="off" />
            <x-field name="password" label="Kata sandi Anda" type="password" :required="true" autocomplete="current-password" />
            <button type="submit" class="btn btn-primary bg-oxblood hover:bg-oxblood" x-bind:aria-busy="busy.toString()" @disabled($preview['places'] + $preview['hotels'] === 0)>Hapus permanen</button>
            @if ($preview['places'] + $preview['hotels'] === 0)
                <p class="text-sm text-ink-soft">Katalog sudah kosong.</p>
            @endif
        </form>
    </div>
</x-layouts.admin>

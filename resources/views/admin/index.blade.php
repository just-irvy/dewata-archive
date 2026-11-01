<x-layouts.admin :title="__('ui.admin.title')">
    <h1 class="text-4xl">{{ __('ui.admin.title') }}</h1>
    <p class="mt-2 typewriter">{{ __('ui.admin.role') }}: {{ auth()->user()->role->value }}</p>

    <section aria-labelledby="ringkasan" class="mt-10">
        <h2 id="ringkasan" class="text-2xl">Isi arsip</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[28rem] text-left">
                <caption class="sr-only">Jumlah record menurut status</caption>
                <thead>
                    <tr class="border-b-2 border-ink text-sm">
                        <th scope="col" class="py-2 pr-4">Jenis</th>
                        <th scope="col" class="py-2 pr-4">Draf</th>
                        <th scope="col" class="py-2 pr-4">Terbit</th>
                        <th scope="col" class="py-2">Diarsipkan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($counts as $label => $row)
                        <tr class="border-b border-ink/20">
                            <th scope="row" class="py-3 pr-4 font-semibold">{{ $label }}</th>
                            <td class="py-3 pr-4">{{ $row['draft'] }}</td>
                            <td class="py-3 pr-4">{{ $row['published'] }}</td>
                            <td class="py-3">{{ $row['archived'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="tinjauan" class="mt-12 grid gap-10 lg:grid-cols-2">
        <div>
            <h2 id="tinjauan" class="text-2xl">Tempat perlu ditinjau</h2>
            <p class="mt-1 text-sm text-ink-soft">Terbit, tetapi ditandai perlu ditinjau atau terakhir diverifikasi lebih dari {{ $days['place'] }} hari lalu.</p>
            <ul class="mt-4 divide-y divide-ink/20 border-y border-ink/20">
                @forelse ($stalePlaces as $place)
                    <li class="flex items-center justify-between gap-4 py-3">
                        <a class="btn btn-link" href="{{ route('admin.places.edit', $place->id) }}">{{ $place->name }}</a>
                        <span class="typewriter">{{ $place->verified_at ? $place->verified_at->translatedFormat('j M Y') : 'belum pernah diperiksa' }}</span>
                    </li>
                @empty
                    <li class="py-3 text-sm text-ink-soft">Tidak ada.</li>
                @endforelse
            </ul>
        </div>

        <div>
            <h2 class="text-2xl">Harga hotel perlu ditinjau</h2>
            <p class="mt-1 text-sm text-ink-soft">Hotel terbit dengan harga yang ditandai perlu ditinjau atau terakhir diverifikasi lebih dari {{ $days['hotel_price'] }} hari lalu.</p>
            <ul class="mt-4 divide-y divide-ink/20 border-y border-ink/20">
                @forelse ($staleHotels as $hotel)
                    <li class="flex items-center justify-between gap-4 py-3">
                        <a class="btn btn-link" href="{{ route('admin.hotels.edit', $hotel->id) }}">{{ $hotel->name }}</a>
                        <span class="typewriter">{{ $hotel->verified_at ? $hotel->verified_at->translatedFormat('j M Y') : 'belum pernah diperiksa' }}</span>
                    </li>
                @empty
                    <li class="py-3 text-sm text-ink-soft">Tidak ada.</li>
                @endforelse
            </ul>
        </div>
    </section>

    @can('reset-catalog')
        <section aria-labelledby="zona-berbahaya" class="mt-16 border-t-2 border-oxblood pt-6">
            <h2 id="zona-berbahaya" class="text-2xl text-oxblood">Zona berbahaya</h2>
            <p class="mt-2 max-w-[62ch] text-sm text-ink-soft">Hapus permanen semua tempat dan hotel untuk mengganti katalog dari nol. Hanya super-admin.</p>
            <p class="mt-3"><a class="btn btn-link text-oxblood" href="{{ route('admin.catalog-reset.show') }}">Hapus semua tempat dan hotel</a></p>
        </section>
    @endcan
</x-layouts.admin>

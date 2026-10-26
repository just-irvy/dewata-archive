<x-layouts.admin title="Hotel">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">Hotel</h1>
        <x-button :href="route('admin.hotels.create')">Hotel baru</x-button>
    </div>

    <form method="GET" action="{{ route('admin.hotels.index') }}" class="mt-6 grid gap-4 sm:grid-cols-[1fr_12rem_12rem_auto] sm:items-end">
        <div class="field">
            <label for="q" class="field-label">Cari nama</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="field-input">
        </div>
        <x-select name="status" label="Status" :options="['draft' => 'Draf', 'published' => 'Terbit', 'archived' => 'Diarsipkan']" :selected="$filters['status']" placeholder="Semua" />
        <x-select name="region" label="Wilayah" :options="$regions->pluck('name', 'id')->all()" :selected="$filters['region']" placeholder="Semua" />
        <x-button type="submit" variant="secondary">Terapkan</x-button>
    </form>

    <div class="mt-8 overflow-x-auto">
        <table class="w-full min-w-[44rem] text-left">
            <caption class="sr-only">Daftar hotel</caption>
            <thead>
                <tr class="border-b-2 border-ink text-sm">
                    <th scope="col" class="py-2 pr-4">Nama</th>
                    <th scope="col" class="py-2 pr-4">Wilayah</th>
                    <th scope="col" class="py-2 pr-4">Jenis</th>
                    <th scope="col" class="py-2 pr-4">Harga per malam</th>
                    <th scope="col" class="py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hotels as $hotel)
                    <tr class="border-b border-ink/20">
                        <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route('admin.hotels.edit', $hotel->id) }}">{{ $hotel->name }}</a></th>
                        <td class="py-3 pr-4">{{ $hotel->region?->name }}</td>
                        <td class="py-3 pr-4">{{ $hotel->type->label() }}</td>
                        <td class="py-3 pr-4 text-sm">
                            @if ($hotel->hasPrice())
                                Rp{{ number_format($hotel->price_min ?? $hotel->price_max, 0, ',', '.') }}@if ($hotel->price_max && $hotel->price_max !== $hotel->price_min) sampai Rp{{ number_format($hotel->price_max, 0, ',', '.') }}@endif
                            @else
                                Tidak tersedia
                            @endif
                        </td>
                        <td class="py-3"><x-status-badge :status="$hotel->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-ink-soft">Belum ada hotel yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $hotels->links() }}</div>
</x-layouts.admin>

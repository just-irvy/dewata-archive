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

    <x-admin.bulk-report type="hotels" />

    <form method="POST" action="{{ route('admin.bulk-status', 'hotels') }}">
        @csrf
        <input type="hidden" name="region" value="{{ $filters['region'] }}">
        <input type="hidden" name="q" value="{{ $filters['q'] }}">

        <div class="mt-6 flex flex-wrap items-center gap-3 border-y border-ink/25 py-3">
            <button type="submit" name="scope" value="selected" class="btn btn-secondary">Terbitkan yang dipilih</button>
            <button type="submit" name="scope" value="ready" class="btn btn-primary" onclick="return confirm('Terbitkan semua draf yang siap? Draf yang belum memenuhi syarat dilewati.')">Terbitkan semua draf yang siap</button>
            <p class="text-sm text-ink-soft">Hotel berharga baru siap bila jenis sumber dan status verifikasi harganya terisi.</p>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[54rem] text-left">
                <caption class="sr-only">Daftar hotel</caption>
                <thead>
                    <tr class="border-b-2 border-ink text-sm">
                        <th scope="col" class="w-10 py-2 pr-2">
                            <input type="checkbox" class="size-5 accent-rust" aria-label="Pilih semua draf di halaman ini" x-data x-on:change="$el.closest('form').querySelectorAll('input[name=\'ids[]\']').forEach(c => c.checked = $event.target.checked)">
                        </th>
                        <th scope="col" class="py-2 pr-4">Nama</th>
                        <th scope="col" class="py-2 pr-4">Wilayah</th>
                        <th scope="col" class="py-2 pr-4">Harga per malam</th>
                        <th scope="col" class="py-2 pr-4">Status</th>
                        <th scope="col" class="py-2">Kesiapan terbit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hotels as $hotel)
                        @php($ready = $readiness[$hotel->id] ?? null)
                        <tr class="border-b border-ink/20 align-top">
                            <td class="py-3 pr-2">
                                @if ($ready)
                                    <input type="checkbox" name="ids[]" value="{{ $hotel->id }}" class="size-5 accent-rust" aria-label="Pilih {{ $hotel->name }}">
                                @endif
                            </td>
                            <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route('admin.hotels.edit', $hotel->id) }}">{{ $hotel->name }}</a></th>
                            <td class="py-3 pr-4">{{ $hotel->region?->name }}</td>
                            <td class="py-3 pr-4 text-sm">
                                @if ($hotel->hasPrice())
                                    Rp{{ number_format($hotel->price_min ?? $hotel->price_max, 0, ',', '.') }}@if ($hotel->price_max && $hotel->price_max !== $hotel->price_min) sampai Rp{{ number_format($hotel->price_max, 0, ',', '.') }}@endif
                                @else
                                    Tidak tersedia
                                @endif
                            </td>
                            <td class="py-3 pr-4"><x-status-badge :status="$hotel->status" /></td>
                            <td class="py-3 text-sm">
                                @if ($ready === null)
                                    <span class="text-ink-soft">Tidak berlaku</span>
                                @elseif ($ready->passes())
                                    <span class="font-semibold">Siap diterbitkan</span>
                                @else
                                    <span class="font-semibold text-oxblood">Kurang {{ count($ready->blockers) }}</span>
                                    <span class="block text-ink-soft">{{ $ready->blockers[0] }}@if (count($ready->blockers) > 1) dan {{ count($ready->blockers) - 1 }} lainnya @endif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-ink-soft">Belum ada hotel yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-6">{{ $hotels->links() }}</div>
</x-layouts.admin>

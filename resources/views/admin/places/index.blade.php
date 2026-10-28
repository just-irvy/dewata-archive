<x-layouts.admin title="Tempat">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">Tempat</h1>
        <x-button :href="route('admin.places.create')">Tempat baru</x-button>
    </div>

    <form method="GET" action="{{ route('admin.places.index') }}" class="mt-6 grid gap-4 sm:grid-cols-[1fr_12rem_12rem_auto] sm:items-end">
        <div class="field">
            <label for="q" class="field-label">Cari nama</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="field-input">
        </div>
        <x-select name="status" label="Status" :options="['draft' => 'Draf', 'published' => 'Terbit', 'archived' => 'Diarsipkan']" :selected="$filters['status']" placeholder="Semua" />
        <x-select name="region" label="Wilayah" :options="$regions->pluck('name', 'id')->all()" :selected="$filters['region']" placeholder="Semua" />
        <x-button type="submit" variant="secondary">Terapkan</x-button>
    </form>

    <x-admin.bulk-report type="places" />

    <form method="POST" action="{{ route('admin.bulk-status', 'places') }}">
        @csrf
        <input type="hidden" name="region" value="{{ $filters['region'] }}">
        <input type="hidden" name="q" value="{{ $filters['q'] }}">

        <div class="mt-6 flex flex-wrap items-center gap-3 border-y border-ink/25 py-3">
            <button type="submit" name="scope" value="selected" class="btn btn-secondary">Terbitkan yang dipilih</button>
            <button type="submit" name="scope" value="ready" class="btn btn-primary" onclick="return confirm('Terbitkan semua draf yang siap? Draf yang belum memenuhi syarat dilewati.')">Terbitkan semua draf yang siap</button>
            <p class="text-sm text-ink-soft">Hanya draf yang memenuhi syarat terbit yang diproses. Sisanya dilewati dan alasannya dilaporkan.</p>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[50rem] text-left">
                <caption class="sr-only">Daftar tempat</caption>
                <thead>
                    <tr class="border-b-2 border-ink text-sm">
                        <th scope="col" class="w-10 py-2 pr-2">
                            <input type="checkbox" class="size-5 accent-rust" aria-label="Pilih semua draf di halaman ini" x-data x-on:change="$el.closest('form').querySelectorAll('input[name=\'ids[]\']').forEach(c => c.checked = $event.target.checked)">
                        </th>
                        <th scope="col" class="py-2 pr-4">Nama</th>
                        <th scope="col" class="py-2 pr-4">Wilayah</th>
                        <th scope="col" class="py-2 pr-4">Status</th>
                        <th scope="col" class="py-2 pr-4">Kesiapan terbit</th>
                        <th scope="col" class="py-2">Diperbarui</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($places as $place)
                        @php($ready = $readiness[$place->id] ?? null)
                        <tr class="border-b border-ink/20 align-top">
                            <td class="py-3 pr-2">
                                @if ($ready)
                                    <input type="checkbox" name="ids[]" value="{{ $place->id }}" class="size-5 accent-rust" aria-label="Pilih {{ $place->name }}">
                                @endif
                            </td>
                            <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route('admin.places.edit', $place->id) }}">{{ $place->name }}</a></th>
                            <td class="py-3 pr-4">{{ $place->region?->name }}</td>
                            <td class="py-3 pr-4"><x-status-badge :status="$place->status" /></td>
                            <td class="py-3 pr-4 text-sm">
                                @if ($ready === null)
                                    <span class="text-ink-soft">Tidak berlaku</span>
                                @elseif ($ready->passes())
                                    <span class="font-semibold">Siap diterbitkan</span>
                                @else
                                    <span class="font-semibold text-oxblood">Kurang {{ count($ready->blockers) }}</span>
                                    <span class="block text-ink-soft">{{ $ready->blockers[0] }}@if (count($ready->blockers) > 1) dan {{ count($ready->blockers) - 1 }} lainnya @endif</span>
                                @endif
                            </td>
                            <td class="py-3 typewriter">{{ $place->updated_at->translatedFormat('j M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-ink-soft">Belum ada tempat yang cocok. <a class="btn btn-link" href="{{ route('admin.places.create') }}">Buat tempat pertama</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-6">{{ $places->links() }}</div>
</x-layouts.admin>

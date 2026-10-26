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

    <div class="mt-8 overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left">
            <caption class="sr-only">Daftar tempat</caption>
            <thead>
                <tr class="border-b-2 border-ink text-sm">
                    <th scope="col" class="py-2 pr-4">Nama</th>
                    <th scope="col" class="py-2 pr-4">Wilayah</th>
                    <th scope="col" class="py-2 pr-4">Status</th>
                    <th scope="col" class="py-2 pr-4">Verifikasi</th>
                    <th scope="col" class="py-2">Diperbarui</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($places as $place)
                    <tr class="border-b border-ink/20">
                        <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route('admin.places.edit', $place->id) }}">{{ $place->name }}</a></th>
                        <td class="py-3 pr-4">{{ $place->region?->name }}</td>
                        <td class="py-3 pr-4"><x-status-badge :status="$place->status" /></td>
                        <td class="py-3 pr-4 text-sm">{{ $place->verification_status->label() }}</td>
                        <td class="py-3 typewriter">{{ $place->updated_at->translatedFormat('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-ink-soft">Belum ada tempat yang cocok. <a class="btn btn-link" href="{{ route('admin.places.create') }}">Buat tempat pertama</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $places->links() }}</div>
</x-layouts.admin>

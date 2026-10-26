<x-layouts.admin title="Koleksi">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">Koleksi</h1>
        <x-button :href="route('admin.collections.create')">Koleksi baru</x-button>
    </div>

    <div class="mt-8 overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left">
            <caption class="sr-only">Daftar koleksi editorial</caption>
            <thead>
                <tr class="border-b-2 border-ink text-sm">
                    <th scope="col" class="py-2 pr-4">Judul</th>
                    <th scope="col" class="py-2 pr-4">Tempat</th>
                    <th scope="col" class="py-2 pr-4">Status</th>
                    <th scope="col" class="py-2">Diperbarui</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collections as $collection)
                    <tr class="border-b border-ink/20">
                        <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route('admin.collections.edit', $collection->id) }}">{{ $collection->title }}</a></th>
                        <td class="py-3 pr-4">{{ $collection->places_count }}</td>
                        <td class="py-3 pr-4"><x-status-badge :status="$collection->status" /></td>
                        <td class="py-3 typewriter">{{ $collection->updated_at->translatedFormat('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-ink-soft">Belum ada koleksi. Koleksi mengelompokkan tempat terbit menjadi satu cerita editorial.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $collections->links() }}</div>
</x-layouts.admin>

<x-layouts.admin title="Impor dataset">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">Impor dataset</h1>
        <x-button :href="route('admin.imports.create')">Unggah dataset</x-button>
    </div>
    <p class="mt-2 max-w-[62ch] text-sm text-ink-soft">Berkas dibaca ke area staging dan diperiksa lebih dulu. Data baru masuk ke arsip setelah Anda menyetujui, selalu sebagai draf.</p>

    <div class="mt-8 overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left">
            <caption class="sr-only">Riwayat impor</caption>
            <thead>
                <tr class="border-b-2 border-ink text-sm">
                    <th scope="col" class="py-2 pr-4">Berkas</th>
                    <th scope="col" class="py-2 pr-4">Baris</th>
                    <th scope="col" class="py-2 pr-4">Status</th>
                    <th scope="col" class="py-2 pr-4">Diunggah oleh</th>
                    <th scope="col" class="py-2">Waktu</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr class="border-b border-ink/20">
                        <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route('admin.imports.show', $batch->id) }}">{{ $batch->original_filename }}</a></th>
                        <td class="py-3 pr-4">{{ $batch->totals['rows'] ?? 0 }}</td>
                        <td class="py-3 pr-4 text-sm">{{ ['staged' => 'Menunggu persetujuan', 'committed' => 'Sudah dibuat sebagai draf', 'rolled_back' => 'Di-rollback'][$batch->status] ?? $batch->status }}</td>
                        <td class="py-3 pr-4 text-sm">{{ $batch->uploader?->name ?? 'Akun dihapus' }}</td>
                        <td class="py-3 typewriter">{{ $batch->created_at->translatedFormat('j M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-ink-soft">Belum ada impor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $batches->links() }}</div>
</x-layouts.admin>

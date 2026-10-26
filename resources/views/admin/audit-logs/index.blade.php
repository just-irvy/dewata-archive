<x-layouts.admin title="Audit log">
    <h1 class="text-4xl">Audit log</h1>
    <p class="mt-2 text-sm text-ink-soft">Catatan bersifat tambah-saja. Hanya nilai yang berubah yang tersimpan. Alamat IP disimpan sebagai hash.</p>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mt-6 flex flex-wrap items-end gap-4">
        <x-select name="entity" label="Jenis data" :options="$entities->mapWithKeys(fn ($e) => [$e => $e])->all()" :selected="$entity" placeholder="Semua" />
        <x-button type="submit" variant="secondary">Terapkan</x-button>
    </form>

    <div class="mt-8 overflow-x-auto">
        <table class="w-full min-w-[44rem] text-left">
            <caption class="sr-only">Catatan perubahan oleh admin</caption>
            <thead>
                <tr class="border-b-2 border-ink text-sm">
                    <th scope="col" class="py-2 pr-4">Waktu</th>
                    <th scope="col" class="py-2 pr-4">Pelaku</th>
                    <th scope="col" class="py-2 pr-4">Aksi</th>
                    <th scope="col" class="py-2 pr-4">Data</th>
                    <th scope="col" class="py-2">Perubahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-ink/20 align-top">
                        <td class="py-3 pr-4 typewriter whitespace-nowrap">{{ $log->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td class="py-3 pr-4 text-sm">{{ $log->actor?->name ?? 'Akun dihapus' }}</td>
                        <td class="py-3 pr-4 text-sm font-semibold">{{ $log->action }}</td>
                        <td class="py-3 pr-4 typewriter">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                        <td class="py-3 text-sm">
                            @if ($log->before_json || $log->after_json)
                                <details>
                                    <summary class="cursor-pointer font-semibold text-rust">Lihat</summary>
                                    @if ($log->before_json)<p class="mt-2 font-semibold">Sebelum</p><pre class="typewriter overflow-x-auto whitespace-pre-wrap">{{ json_encode($log->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endif
                                    @if ($log->after_json)<p class="mt-2 font-semibold">Sesudah</p><pre class="typewriter overflow-x-auto whitespace-pre-wrap">{{ json_encode($log->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endif
                                </details>
                            @else
                                <span class="text-ink-soft">Tidak ada detail</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-ink-soft">Belum ada catatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.admin>

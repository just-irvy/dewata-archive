@php
    $t = $batch->totals ?? [];
    $creatable = ($t['places'] ?? 0) + ($t['hotels'] ?? 0);
    $statusLabel = ['staged' => 'Menunggu persetujuan', 'committed' => 'Sudah dibuat sebagai draf', 'rolled_back' => 'Di-rollback'][$batch->status] ?? $batch->status;
    $rowStatus = ['valid' => 'Valid', 'warning' => 'Peringatan', 'error' => 'Error'];
@endphp

<x-layouts.admin :title="$batch->original_filename">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('admin.imports.index') }}">Impor dataset</a></nav>
    <h1 class="mt-3 text-4xl break-words">{{ $batch->original_filename }}</h1>
    <p class="mt-2 typewriter">{{ $statusLabel }}, diunggah {{ $batch->created_at->translatedFormat('j M Y H:i') }} oleh {{ $batch->uploader?->name ?? 'akun yang dihapus' }}</p>

    <section aria-labelledby="ringkasan" class="mt-10">
        <h2 id="ringkasan" class="text-2xl">Ringkasan</h2>
        <dl class="mt-4 grid max-w-2xl grid-cols-2 gap-x-8 gap-y-2 sm:grid-cols-3">
            <div><dt class="text-sm text-ink-soft">Baris dibaca</dt><dd class="text-2xl font-display font-bold">{{ $t['rows'] ?? 0 }}</dd></div>
            <div><dt class="text-sm text-ink-soft">Tempat baru</dt><dd class="text-2xl font-display font-bold">{{ $t['places'] ?? 0 }}</dd></div>
            <div><dt class="text-sm text-ink-soft">Hotel baru</dt><dd class="text-2xl font-display font-bold">{{ $t['hotels'] ?? 0 }}</dd></div>
            <div><dt class="text-sm text-ink-soft">Dengan peringatan</dt><dd class="text-2xl font-display font-bold">{{ $t['warning'] ?? 0 }}</dd></div>
            <div><dt class="text-sm text-ink-soft">Dilewati (sudah ada)</dt><dd class="text-2xl font-display font-bold">{{ $t['skipped'] ?? 0 }}</dd></div>
            <div><dt class="text-sm font-semibold text-oxblood">Error, tidak diproses</dt><dd class="text-2xl font-display font-bold text-oxblood">{{ $t['error'] ?? 0 }}</dd></div>
        </dl>
    </section>

    @if (! empty($batch->notes))
        <section aria-labelledby="catatan" class="mt-10 max-w-3xl border border-ink/25 bg-surface p-4">
            <h2 id="catatan" class="text-xl">Catatan tentang dataset</h2>
            <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                @foreach ($batch->notes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($errors->has('import'))
        <p role="alert" class="mt-8 border-l-4 border-oxblood bg-surface px-4 py-3 text-sm font-semibold text-oxblood">{{ $errors->first('import') }}</p>
    @endif

    @if ($batch->isStaged())
        <section aria-labelledby="setujui" class="mt-10 max-w-3xl border-t-2 border-ink pt-6">
            <h2 id="setujui" class="text-2xl">Setujui impor</h2>
            @if ($creatable > 0)
                <p class="mt-2">Akan dibuat <strong>{{ $creatable }} draf</strong> ({{ $t['places'] ?? 0 }} tempat dan {{ $t['hotels'] ?? 0 }} hotel). Semuanya berstatus draf dengan verifikasi "Belum diketahui" dan tidak terlihat publik. Tiap draf baru bisa terbit setelah sumber, tanggal verifikasi, dan foto dilengkapi di editor.</p>
                <form method="POST" action="{{ route('admin.imports.commit', $batch->id) }}" class="mt-4" x-data="{ busy: false }" x-on:submit="busy = true" onsubmit="return confirm('Buat {{ $creatable }} draf dari berkas ini?')">
                    @csrf
                    <x-button type="submit"><span x-text="busy ? 'Membuat draf' : 'Setujui dan buat draf'">Setujui dan buat draf</span></x-button>
                </form>
            @else
                <p class="mt-2 text-ink-soft">Tidak ada baris yang bisa dibuat. Perbaiki berkas lalu unggah ulang.</p>
            @endif
        </section>
    @elseif ($batch->isCommitted())
        <section aria-labelledby="hasil" class="mt-10 max-w-3xl border-t-2 border-ink pt-6">
            <h2 id="hasil" class="text-2xl">Sudah dibuat</h2>
            <p class="mt-2">Dibuat {{ $t['result']['created_places'] ?? 0 }} tempat dan {{ $t['result']['created_hotels'] ?? 0 }} hotel sebagai draf pada {{ $batch->committed_at?->translatedFormat('j M Y H:i') }}.</p>
            <p class="mt-3"><a class="btn btn-secondary" href="{{ route('admin.places.index', ['status' => 'draft']) }}">Buka draf tempat</a> <a class="btn btn-secondary" href="{{ route('admin.hotels.index', ['status' => 'draft']) }}">Buka draf hotel</a></p>

            @can('rollback-import')
                <form method="POST" action="{{ route('admin.imports.rollback', $batch->id) }}" class="mt-8" onsubmit="return confirm('Hapus permanen draf dari impor ini yang belum dikerjakan?')">
                    @csrf
                    <p class="text-sm text-ink-soft">Rollback menghapus permanen draf yang belum diubah, belum punya foto, dan belum dipakai. Yang sudah dikerjakan editor dipertahankan.</p>
                    <button type="submit" class="btn btn-link text-oxblood">Rollback impor ini</button>
                </form>
            @endcan
        </section>
    @endif

    @if (! $batch->isCommitted() || auth()->user()->can('rollback-import'))
        <form method="POST" action="{{ route('admin.imports.destroy', $batch->id) }}" class="mt-12 max-w-3xl border-t border-brass/60 pt-4"
              onsubmit="return confirm('{{ $batch->isCommitted() ? 'Hapus riwayat impor ini? Tempat dan hotel yang sudah dibuat tetap ada, tetapi Rollback tidak bisa dipakai lagi.' : 'Batalkan dan hapus impor ini? Belum ada data yang dibuat.' }}')">
            @csrf
            @method('DELETE')
            <p class="text-sm text-ink-soft">
                @if ($batch->isCommitted())
                    Menghapus riwayat tidak menghapus tempat dan hotel yang sudah dibuat. Rollback untuk batch ini tidak akan tersedia lagi.
                @else
                    Menghapus batch ini membuang berkas dan seluruh barisnya dari area staging. Tidak ada tempat atau hotel yang dibuat atau dihapus.
                @endif
            </p>
            <button type="submit" class="btn btn-link text-oxblood">{{ $batch->isCommitted() ? 'Hapus riwayat impor' : 'Batalkan dan hapus impor ini' }}</button>
        </form>
    @endif

    <section aria-labelledby="baris" class="mt-14">
        <h2 id="baris" class="text-2xl">Baris</h2>
        <nav aria-label="Saring baris" class="mt-3 flex flex-wrap gap-2">
            @foreach ([null => 'Semua', 'error' => 'Error', 'warning' => 'Peringatan', 'valid' => 'Valid'] as $value => $label)
                <a class="btn min-h-9 px-3 text-sm {{ ($filter ?? null) === $value ? 'border-ink bg-ink text-paper' : 'btn-secondary' }}" @if (($filter ?? null) === $value) aria-current="page" @endif href="{{ route('admin.imports.show', array_filter(['batch' => $batch->id, 'status' => $value])) }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[52rem] text-left text-sm">
                <caption class="sr-only">Baris hasil parse dengan isu validasinya</caption>
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th scope="col" class="py-2 pr-3">Baris</th>
                        <th scope="col" class="py-2 pr-3">Nama</th>
                        <th scope="col" class="py-2 pr-3">Jenis</th>
                        <th scope="col" class="py-2 pr-3">Wilayah</th>
                        <th scope="col" class="py-2 pr-3">Status</th>
                        <th scope="col" class="py-2">Isu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-ink/20 align-top">
                            <td class="py-3 pr-3 typewriter">{{ $row->row_number }}</td>
                            <th scope="row" class="py-3 pr-3 font-semibold">{{ $row->payload['name'] ?? '-' }}</th>
                            <td class="py-3 pr-3">{{ ['place' => 'Tempat', 'hotel' => 'Hotel'][$row->entity] ?? '-' }}</td>
                            <td class="py-3 pr-3">{{ \Illuminate\Support\Str::headline($row->payload['region_slug'] ?? '-') }}@if (! empty($row->payload['area_label'])), {{ $row->payload['area_label'] }}@endif</td>
                            <td class="py-3 pr-3">
                                <span @class(['inline-flex min-h-7 items-center px-2 text-xs font-bold', 'border border-oxblood text-oxblood' => $row->status === 'error', 'border border-dashed border-ink' => $row->status === 'warning', 'border border-ink bg-ink text-paper' => $row->status === 'valid'])>{{ $rowStatus[$row->status] ?? $row->status }}</span>
                                @if ($row->action === 'skip')<span class="typewriter block">dilewati</span>@endif
                                @if ($row->action === 'created')<span class="typewriter block">dibuat</span>@endif
                                @if ($row->action === 'rolled_back')<span class="typewriter block">di-rollback</span>@endif
                            </td>
                            <td class="py-3">
                                @forelse ($row->issues as $issue)
                                    <p class="{{ $issue['level'] === 'error' ? 'font-semibold text-oxblood' : '' }}"><span class="typewriter">{{ $issue['level'] === 'error' ? 'Error' : 'Peringatan' }}:</span> {{ $issue['message'] }}</p>
                                @empty
                                    <span class="text-ink-soft">Tidak ada</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $rows->links() }}</div>
    </section>
</x-layouts.admin>

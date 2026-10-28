<x-layouts.admin title="Unggah dataset">
    <h1 class="text-4xl">Unggah dataset</h1>

    <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="mt-10 max-w-xl space-y-5" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <div class="field">
            <label for="file" class="field-label">Berkas CSV</label>
            <p class="field-hint">
                Format .csv atau .txt, UTF-8, pemisah koma, maksimal {{ config('archive.import.max_kb') / 1024 }} MB dan {{ config('archive.import.max_rows') }} baris.
                Kolom wajib: {{ implode(', ', \App\Imports\DatasetMapper::REQUIRED_COLUMNS) }}, ditambah kabupaten atau location_area.
                Kolom opsional: price_min, price_max, price_source, theme, hotel_type, source_url, source_date, summary, verified_at, verification_status.
            </p>
            <input id="file" type="file" name="file" accept=".csv,.txt,text/csv,text/plain" required class="field-input">
            @error('file')<p class="field-error"><span>{{ $message }}</span></p>@enderror
        </div>

        <div class="border-l-4 border-moss bg-surface px-4 py-3 text-sm">
            <p class="font-semibold">Yang terjadi setelah ini</p>
            <p class="mt-1">Berkas dibaca dan diperiksa, tetapi belum ada tempat atau hotel yang dibuat. Anda melihat laporan dulu dan baru menyetujuinya.</p>
        </div>

        <x-button type="submit"><span x-text="busy ? 'Membaca berkas' : 'Baca dan periksa'">Baca dan periksa</span></x-button>
    </form>
</x-layouts.admin>

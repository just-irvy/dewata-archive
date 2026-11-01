<x-layouts.admin title="Beranda">
    <h1 class="text-4xl">Foto sampul beranda</h1>
    <p class="mt-2 max-w-[62ch] text-sm text-ink-soft">
        Foto Pulau Bali di samping judul beranda. Tanpa foto, atau bila alt text dan hak pakainya belum lengkap, beranda menampilkan lembar peta Bali beserta titik tempat yang terbit.
    </p>

    <div class="mt-8 grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <h2 class="text-2xl">Tampil sekarang</h2>
            @if ($live)
                <figure class="mt-4">
                    <img src="{{ $live['url'] }}" alt="{{ $live['alt_text'] }}" @if ($live['width'] ?? null) width="{{ $live['width'] }}" height="{{ $live['height'] }}" @endif class="aspect-[4/5] w-full object-cover">
                    <figcaption class="typewriter mt-2">Foto: {{ collect([$live['credit'] ?? null, $live['license_note'] ?? null])->filter()->join(', ') }}</figcaption>
                </figure>
                <form method="POST" action="{{ route('admin.home.destroy') }}" class="mt-4" x-data x-on:submit="if (! confirm('Hapus foto sampul? Beranda kembali menampilkan peta Bali.')) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link text-oxblood">Hapus foto sampul</button>
                </form>
            @else
                <x-bali-plate class="mt-4" :points="[]" :total="0" />
                <p class="mt-3 text-sm text-ink-soft">Peta tampil karena foto sampul belum dipasang{{ $cover ? ' atau belum lengkap' : '' }}.</p>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.home.update') }}" enctype="multipart/form-data" class="space-y-6 lg:col-span-7" x-data="{ busy: false }" x-on:submit="busy = true">
            @csrf
            <h2 class="text-2xl">{{ $cover ? 'Ganti atau ubah keterangan' : 'Pasang foto' }}</h2>

            <div class="field">
                <label for="file" class="field-label">Foto @if ($cover)<span class="field-optional">(opsional, kosongkan bila hanya mengubah keterangan)</span>@endif</label>
                <p id="file-hint" class="field-hint">JPG, PNG, atau WebP, lebar minimal 1000 piksel. Foto potret atau persegi paling cocok; tampilan memotong ke rasio 4:5. Metadata lokasi kamera dibuang otomatis.</p>
                <input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="file-hint" @class(['field-input', 'field-input-error' => $errors->has('file')]) @required(! $cover)>
                @error('file')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <x-field name="alt_text" label="Alt text" :required="true" maxlength="255" :value="$cover['alt_text'] ?? null" hint="Jelaskan isi foto untuk pembaca layar, misalnya: Sawah berundak di lereng Gunung Batukaru saat pagi berkabut." />
            <x-field name="caption" label="Keterangan" maxlength="255" :value="$cover['caption'] ?? null" hint="Tampil di bawah foto. Misalnya: Jatiluwih, Tabanan." />
            <x-field name="credit" label="Kredit foto" maxlength="255" :value="$cover['credit'] ?? null" hint="Nama fotografer atau pemilik. Wajib bila lisensi kosong." />
            <x-field name="license_note" label="Lisensi" maxlength="255" :value="$cover['license_note'] ?? null" hint="Misalnya: Foto milik sendiri, CC BY 4.0, atau Lisensi Unsplash. Wajib bila kredit kosong." />

            <p class="border-l-4 border-brass bg-surface px-4 py-3 text-sm">Isi hanya bila Anda berhak memakai foto itu. Foto dari Google Maps, Instagram, atau situs lain tanpa izin bukan milik Anda.</p>

            <button type="submit" class="btn btn-primary" x-bind:aria-busy="busy.toString()">Simpan foto sampul</button>
        </form>
    </div>
</x-layouts.admin>

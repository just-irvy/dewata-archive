@props(['owner', 'type'])

<section id="foto" aria-labelledby="foto-judul" class="scroll-mt-6 space-y-6">
    <h2 id="foto-judul" class="text-2xl">Foto</h2>

    @forelse ($owner->media as $media)
        <div class="grid gap-4 border border-ink/25 bg-surface p-4 md:grid-cols-[10rem_1fr]">
            <img src="{{ $media->url() }}" alt="{{ $media->alt_text }}" @if ($media->width) width="{{ $media->width }}" height="{{ $media->height }}" @endif class="h-auto w-full max-w-40 object-cover" loading="lazy">

            <div class="space-y-3">
                @unless ($media->isPublishable())
                    <p class="text-sm font-bold text-oxblood">Belum lengkap. Isi alt text dan kredit atau lisensi sebelum terbit.</p>
                @endunless

                {{-- Input polos, bukan x-field, agar old() dari form unggah tidak bocor ke form ini. --}}
                <form method="POST" action="{{ route('admin.media.update', $media->id) }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    @foreach ([['alt_text', 'Alt text', 'text'], ['caption', 'Keterangan', 'text'], ['credit', 'Kredit', 'text'], ['license_note', 'Lisensi atau izin', 'text'], ['sort_order', 'Urutan', 'number']] as [$field, $label, $inputType])
                        <div class="field">
                            <label for="media-{{ $media->id }}-{{ $field }}" class="field-label">{{ $label }}</label>
                            <input id="media-{{ $media->id }}-{{ $field }}" name="{{ $field }}" type="{{ $inputType }}" value="{{ $media->{$field} }}" class="field-input">
                        </div>
                    @endforeach
                    <div class="flex items-end"><x-button type="submit" variant="secondary">Simpan keterangan</x-button></div>
                </form>

                <form method="POST" action="{{ route('admin.media.destroy', $media->id) }}" onsubmit="return confirm('Hapus foto ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link text-oxblood">Hapus foto</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-ink-soft">Belum ada foto.</p>
    @endforelse

    <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="grid gap-4 border border-dashed border-ink p-4 sm:grid-cols-2">
        @csrf
        <input type="hidden" name="mediable_type" value="{{ $type }}">
        <input type="hidden" name="mediable_id" value="{{ $owner->id }}">

        <div class="field sm:col-span-2">
            <label for="upload-file" class="field-label">Unggah foto baru</label>
            <p class="field-hint">JPG, PNG, atau WebP. Maksimal {{ config('archive.media.max_kb') / 1024 }} MB. SVG tidak diterima.</p>
            <input id="upload-file" type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="field-input">
            @error('file')<p class="field-error"><span>{{ $message }}</span></p>@enderror
        </div>
        <x-field name="alt_text" label="Alt text" hint="Jelaskan isi foto untuk pembaca layar." id="upload-alt" />
        <x-field name="caption" label="Keterangan" id="upload-caption" />
        <x-field name="credit" label="Kredit" hint="Nama fotografer atau pemilik." id="upload-credit" />
        <x-field name="license_note" label="Lisensi atau izin" hint="Contoh: izin tertulis, CC BY 4.0." id="upload-license" />
        <div class="sm:col-span-2"><x-button type="submit">Unggah foto</x-button></div>
    </form>
</section>

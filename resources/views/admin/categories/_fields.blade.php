<x-textarea name="description" label="Deskripsi" :value="$record->description" :rows="4" />
<x-field name="icon_key" label="Kunci ikon" :value="$record->icon_key" hint="Huruf, angka, strip, dan garis bawah." />
<label class="flex min-h-11 items-center gap-3">
    <input type="hidden" name="is_published" value="0">
    <input type="checkbox" name="is_published" value="1" class="size-5 accent-rust" @checked(old('is_published', $record->is_published))>
    <span>Terbit</span>
</label>

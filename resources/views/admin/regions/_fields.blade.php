<x-select name="region_type" label="Jenis wilayah" :options="['administrative' => 'Administratif (kabupaten atau kota)', 'editorial' => 'Area editorial']" :selected="$record->region_type?->value ?? 'administrative'" hint="Jangan mencampur batas administratif dengan area pemasaran tanpa penjelasan." required />
<x-textarea name="description" label="Deskripsi" :value="$record->description" :rows="4" />
<label class="flex min-h-11 items-center gap-3">
    <input type="hidden" name="is_published" value="0">
    <input type="checkbox" name="is_published" value="1" class="size-5 accent-rust" @checked(old('is_published', $record->is_published))>
    <span>Terbit</span>
</label>

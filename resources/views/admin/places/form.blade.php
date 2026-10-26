@php
    $isNew = ! $place->exists;
    $locked = ! $isNew && $place->isPublished();
    $verif = [
        'unknown' => 'Belum diketahui',
        'needs_review' => 'Perlu ditinjau',
        'verified' => 'Terverifikasi',
    ];
    $tri = ['unknown' => 'Belum diketahui', 'yes' => 'Ya', 'no' => 'Tidak'];
    $tagGroups = $tags->groupBy(fn ($tag) => $tag->tag_type->value);
    $tagTitles = ['theme' => 'Tema', 'attribute' => 'Atribut', 'accessibility' => 'Aksesibilitas'];
@endphp

<x-layouts.admin :title="$isNew ? 'Tempat baru' : $place->name">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-4xl">{{ $isNew ? 'Tempat baru' : $place->name }}</h1>
            @unless ($isNew)
                <p class="mt-2 flex items-center gap-3"><x-status-badge :status="$place->status" /> <span class="typewriter">/places/{{ $place->slug }}</span></p>
            @endunless
        </div>
        @unless ($isNew)
            <x-button :href="route('admin.places.preview', $place->id)" variant="secondary">Pratinjau</x-button>
        @endunless
    </div>

    @unless ($isNew)
        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-start">
            <x-admin.readiness :report="$report" />
            <x-admin.status-actions :record="$place" type="places" />
        </div>
    @endunless

    <form method="POST" action="{{ $isNew ? route('admin.places.store') : route('admin.places.update', $place->id) }}" class="mt-12 space-y-12" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Identitas</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" label="Nama tempat" :value="$place->name" required />
                <x-field name="slug" label="Slug URL" :value="$place->slug" :hint="$locked ? 'Dikunci karena sudah terbit.' : 'Kosongkan untuk dibuat dari nama. Tidak bisa diubah setelah terbit.'" :readonly="$locked" />
            </div>
            <x-select name="region_id" label="Wilayah" :options="$regions->pluck('name', 'id')->all()" :selected="$place->region_id" placeholder="Pilih wilayah" required />
            <x-checks name="category_ids" legend="Kategori" hint="Minimal satu kategori yang sudah terbit untuk bisa diterbitkan." :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->is_published ? '' : ' (draf)')])->all()" :checked="$place->categories->modelKeys()" />
            @foreach ($tagGroups as $group => $items)
                <x-checks name="tag_ids" :legend="'Tag '.strtolower($tagTitles[$group])" :options="$items->pluck('name', 'id')->all()" :checked="$place->tags->modelKeys()" />
            @endforeach
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Konten</legend>
            <x-textarea name="summary" label="Ringkasan" hint="Satu atau dua kalimat spesifik. Maksimal 300 karakter." :value="$place->summary" :rows="3" required />
            <x-textarea name="body" label="Uraian" hint="Teks biasa. Pisahkan paragraf dengan baris kosong." :value="$place->body" :rows="10" />
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Lokasi</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="lat" label="Lintang (latitude)" type="text" inputmode="decimal" :value="$place->lat" hint="Contoh: -8.5069000" required />
                <x-field name="lng" label="Bujur (longitude)" type="text" inputmode="decimal" :value="$place->lng" hint="Contoh: 115.2625000" required />
            </div>
            <x-field name="address" label="Alamat" :value="$place->address" />
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Sumber dan verifikasi</legend>
            <x-field name="source_url" label="Tautan sumber" type="url" :value="$place->source_url" hint="Wajib untuk terbit. Gunakan sumber resmi bila ada." />
            <div class="grid gap-5 sm:grid-cols-3">
                <x-select name="verification_status" label="Status verifikasi" :options="$verif" :selected="$place->verification_status?->value ?? 'unknown'" required />
                <x-field name="verified_at" label="Tanggal verifikasi" type="date" :value="$place->verified_at?->format('Y-m-d')" />
                <x-field name="source_updated_at" label="Sumber diperbarui pada" type="date" :value="$place->source_updated_at?->format('Y-m-d')" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="attribution" label="Atribusi atau lisensi data" :value="$place->attribution" />
                <x-field name="external_id" label="ID eksternal" :value="$place->external_id" hint="Dari dataset sumber, bila ada." />
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Informasi kunjungan</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="typical_visit_minutes" label="Perkiraan durasi kunjungan (menit)" type="number" :value="$place->typical_visit_minutes" />
                <x-field name="price_info" label="Informasi harga tiket" :value="$place->price_info" hint="Teks bebas dengan sumber yang sama. Jangan menebak." />
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Jam buka</legend>
            <p class="field-hint">Kosongkan semua bila tidak ada sumber. Jam buka tanpa verifikasi tidak ditampilkan ke publik.</p>
            <div class="space-y-3">
                @foreach (config('archive.weekdays') as $key => $label)
                    @php($hours = $place->opening_hours[$key] ?? [])
                    <div role="group" aria-label="{{ $label }}" class="grid grid-cols-[5.5rem_1fr_1fr] items-end gap-3 sm:grid-cols-[6rem_1fr_1fr_6rem]">
                        <p class="pb-3 font-semibold">{{ $label }}</p>
                        <x-field :name="'hours['.$key.'][open]'" :label="$label.', buka'" type="time" :value="$hours['open'] ?? null" :hideLabel="true" />
                        <x-field :name="'hours['.$key.'][close]'" :label="$label.', tutup'" type="time" :value="$hours['close'] ?? null" :hideLabel="true" />
                        <label class="col-span-3 flex min-h-11 items-center gap-2 text-sm sm:col-span-1">
                            <input type="checkbox" name="hours[{{ $key }}][closed]" value="1" class="size-5 accent-rust" @checked(old("hours.$key.closed", $hours['closed'] ?? false))>
                            Libur
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="max-w-xs"><x-select name="opening_hours_status" label="Status jam buka" :options="$verif" :selected="$place->opening_hours_status?->value ?? 'unknown'" required /></div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Aksesibilitas</legend>
            <p class="field-hint">Isi hanya bila ada sumber. "Belum diketahui" tidak ditampilkan ke publik.</p>
            <div class="grid gap-5 sm:grid-cols-3">
                @foreach (config('archive.accessibility_attributes') as $key => $label)
                    <x-select :name="'accessibility['.$key.']'" :label="$label" :options="$tri" :selected="$place->accessibility[$key] ?? 'unknown'" />
                @endforeach
            </div>
            <div class="max-w-xs"><x-select name="accessibility_status" label="Status aksesibilitas" :options="$verif" :selected="$place->accessibility_status?->value ?? 'unknown'" required /></div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-4 border-t border-brass/60 pt-6">
            <x-button type="submit">Simpan draf</x-button>
            <a class="btn btn-link" href="{{ route('admin.places.index') }}">Kembali ke daftar</a>
        </div>
    </form>

    @unless ($isNew)
        <div class="mt-16 border-t border-brass/60 pt-10">
            <x-admin.media-panel :owner="$place" type="place" />
        </div>

        <form method="POST" action="{{ route('admin.places.destroy', $place->id) }}" class="mt-16 border-t border-brass/60 pt-6" onsubmit="return confirm('Hapus tempat ini?')">
            @csrf
            @method('DELETE')
            <p class="text-sm text-ink-soft">Tempat yang terbit atau sudah dipakai di rencana pengguna tidak bisa dihapus. Arsipkan saja.</p>
            <button type="submit" class="btn btn-link text-oxblood">Hapus tempat</button>
        </form>
    @endunless
</x-layouts.admin>

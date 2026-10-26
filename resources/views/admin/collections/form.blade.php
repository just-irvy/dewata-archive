@php
    $isNew = ! $collection->exists;
    $locked = ! $isNew && $collection->status->value === 'published';
@endphp

<x-layouts.admin :title="$isNew ? 'Koleksi baru' : $collection->title">
    <h1 class="text-4xl">{{ $isNew ? 'Koleksi baru' : $collection->title }}</h1>

    @unless ($isNew)
        <p class="mt-2 flex items-center gap-3"><x-status-badge :status="$collection->status" /> <span class="typewriter">/collections/{{ $collection->slug }}</span></p>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-start">
            <x-admin.readiness :report="$report" />
            <x-admin.status-actions :record="$collection" type="collections" />
        </div>
    @endunless

    <form method="POST" action="{{ $isNew ? route('admin.collections.store') : route('admin.collections.update', $collection->id) }}" class="mt-12 max-w-2xl space-y-5" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-field name="title" label="Judul" :value="$collection->title" required />
        <x-field name="slug" label="Slug URL" :value="$collection->slug" :hint="$locked ? 'Dikunci karena sudah terbit.' : 'Kosongkan untuk dibuat dari judul.'" :readonly="$locked" />
        <x-textarea name="dek" label="Pengantar (dek)" :value="$collection->dek" :rows="3" hint="Satu atau dua kalimat. Maksimal 300 karakter." />

        @unless ($isNew)
            <x-select
                name="cover_media_id"
                label="Foto sampul"
                :options="$coverOptions->mapWithKeys(fn ($m) => [$m->id => ($collection->places->firstWhere('id', $m->mediable_id)?->name ?? 'Tempat').', foto #'.$m->id])->all()"
                :selected="$collection->cover_media_id"
                placeholder="Tanpa sampul"
                hint="Dipilih dari foto tempat yang ada di koleksi ini."
            />
        @endunless

        <div class="flex flex-wrap items-center gap-4 border-t border-brass/60 pt-6">
            <x-button type="submit">Simpan</x-button>
            <a class="btn btn-link" href="{{ route('admin.collections.index') }}">Kembali ke daftar</a>
        </div>
    </form>

    @unless ($isNew)
        <section aria-labelledby="isi-koleksi" class="mt-16 border-t border-brass/60 pt-10">
            <h2 id="isi-koleksi" class="text-2xl">Tempat dalam koleksi</h2>

            <ul class="mt-6 space-y-4">
                @forelse ($collection->places as $place)
                    <li class="border border-ink/25 bg-surface p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-semibold"><a class="btn btn-link" href="{{ route('admin.places.edit', $place->id) }}">{{ $place->name }}</a> <x-status-badge :status="$place->status" /></p>
                            <form method="POST" action="{{ route('admin.collections.places.destroy', [$collection->id, $place->id]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link text-oxblood">Lepas</button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('admin.collections.places.update', [$collection->id, $place->id]) }}" class="mt-3 grid gap-3 sm:grid-cols-[6rem_1fr_auto] sm:items-end">
                            @csrf
                            @method('PATCH')
                            <div class="field">
                                <label for="order-{{ $place->id }}" class="field-label">Urutan</label>
                                <input id="order-{{ $place->id }}" name="sort_order" type="number" min="0" value="{{ $place->pivot->sort_order }}" class="field-input">
                            </div>
                            <div class="field">
                                <label for="note-{{ $place->id }}" class="field-label">Catatan editor</label>
                                <input id="note-{{ $place->id }}" name="editor_note" type="text" value="{{ $place->pivot->editor_note }}" class="field-input">
                            </div>
                            <x-button type="submit" variant="secondary">Simpan</x-button>
                        </form>
                    </li>
                @empty
                    <li class="text-ink-soft">Belum ada tempat. Tambahkan tempat yang sudah terbit di bawah.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('admin.collections.places.store', $collection->id) }}" class="mt-8 grid gap-4 border border-dashed border-ink p-4 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <x-select name="place_id" label="Tambah tempat" :options="$candidates->pluck('name', 'id')->all()" placeholder="Pilih tempat terbit" hint="Hanya tempat yang sudah terbit yang bisa ditambahkan." required />
                </div>
                <x-field name="sort_order" label="Urutan" type="number" min="0" hint="Kosongkan untuk menaruh di akhir." />
                <x-field name="editor_note" label="Catatan editor" />
                <div class="sm:col-span-2"><x-button type="submit">Tambahkan</x-button></div>
            </form>
        </section>

        <form method="POST" action="{{ route('admin.collections.destroy', $collection->id) }}" class="mt-16 border-t border-brass/60 pt-6" onsubmit="return confirm('Hapus koleksi ini?')">
            @csrf
            @method('DELETE')
            <p class="text-sm text-ink-soft">Koleksi yang terbit tidak bisa dihapus. Arsipkan dulu.</p>
            <button type="submit" class="btn btn-link text-oxblood">Hapus koleksi</button>
        </form>
    @endunless
</x-layouts.admin>

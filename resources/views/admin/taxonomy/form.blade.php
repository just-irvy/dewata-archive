@php($isNew = ! $record->exists)

<x-layouts.admin :title="$isNew ? ucfirst($config['singular']).' baru' : $record->name">
    <h1 class="text-4xl">{{ $isNew ? ucfirst($config['singular']).' baru' : $record->name }}</h1>

    <form method="POST" action="{{ $isNew ? route($config['route'].'.store') : route($config['route'].'.update', $record->id) }}" class="mt-10 max-w-2xl space-y-5" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-field name="name" label="Nama" :value="$record->name" required />
        <x-field name="slug" label="Slug" :value="$record->slug" hint="Kosongkan untuk dibuat dari nama." />
        @include($config['fields'], ['record' => $record])

        <div class="flex flex-wrap items-center gap-4 border-t border-brass/60 pt-6">
            <x-button type="submit">Simpan</x-button>
            <a class="btn btn-link" href="{{ route($config['route'].'.index') }}">Kembali ke daftar</a>
        </div>
    </form>

    @unless ($isNew)
        <form method="POST" action="{{ route($config['route'].'.destroy', $record->id) }}" class="mt-16 max-w-2xl border-t border-brass/60 pt-6" onsubmit="return confirm('Hapus {{ $config['singular'] }} ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-link text-oxblood">Hapus {{ $config['singular'] }}</button>
        </form>
    @endunless
</x-layouts.admin>

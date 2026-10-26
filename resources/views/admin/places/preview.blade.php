<x-layouts.admin :title="'Pratinjau '.$place->name">
    <div x-data="{ width: 'desktop' }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl">Pratinjau: {{ $place->name }}</h1>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-secondary" x-bind:aria-pressed="(width === 'desktop').toString()" x-on:click="width = 'desktop'">Desktop</button>
                <button type="button" class="btn btn-secondary" x-bind:aria-pressed="(width === 'mobile').toString()" x-on:click="width = 'mobile'">Ponsel</button>
                <a class="btn btn-link" href="{{ route('admin.places.edit', $place->id) }}">Kembali ke editor</a>
            </div>
        </div>

        <div class="mt-6 flex justify-center border border-ink/25 bg-surface p-4">
            <iframe
                title="Pratinjau halaman {{ $place->name }}"
                src="{{ route('admin.places.preview.page', $place->id) }}"
                class="h-[75vh] max-w-full border border-ink/25 bg-paper"
                x-bind:style="width === 'mobile' ? 'width: 390px' : 'width: 100%'"
            ></iframe>
        </div>
    </div>
</x-layouts.admin>

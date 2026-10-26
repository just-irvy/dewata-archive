<x-layouts.admin title="Media">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">Media</h1>
        <a class="btn btn-secondary" href="{{ route('admin.media.index', $incomplete ? [] : ['incomplete' => 1]) }}" aria-pressed="{{ $incomplete ? 'true' : 'false' }}">
            {{ $incomplete ? 'Tampilkan semua' : 'Hanya yang belum lengkap' }}
        </a>
    </div>
    <p class="mt-2 text-sm text-ink-soft">Foto diunggah dari halaman tempat atau hotel. Daftar ini untuk memeriksa alt text dan hak pakai.</p>

    <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($media as $item)
            @php($owner = $item->mediable)
            <li class="border border-ink/25 bg-surface">
                <img src="{{ $item->url() }}" alt="{{ $item->alt_text }}" @if ($item->width) width="{{ $item->width }}" height="{{ $item->height }}" @endif class="aspect-[4/3] w-full object-cover" loading="lazy">
                <div class="space-y-1 p-3 text-sm">
                    @if ($owner)
                        <a class="btn btn-link" href="{{ route($owner instanceof \App\Models\Hotel ? 'admin.hotels.edit' : 'admin.places.edit', $owner->id) }}#foto">{{ $owner->name }}</a>
                    @endif
                    <p>{{ $item->alt_text ?: 'Alt text kosong' }}</p>
                    <p class="typewriter">{{ $item->credit ?: ($item->license_note ?: 'Hak pakai belum dicatat') }}</p>
                    @unless ($item->isPublishable())
                        <p class="font-bold text-oxblood">Belum lengkap</p>
                    @endunless
                </div>
            </li>
        @empty
            <li class="text-ink-soft">Belum ada media.</li>
        @endforelse
    </ul>

    <div class="mt-8">{{ $media->links() }}</div>
</x-layouts.admin>

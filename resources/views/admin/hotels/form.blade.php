@php
    $isNew = ! $hotel->exists;
    $locked = ! $isNew && $hotel->status->value === 'published';
    $verif = ['unknown' => 'Belum diketahui', 'needs_review' => 'Perlu ditinjau', 'verified' => 'Terverifikasi'];
    $types = collect(\App\Enums\HotelType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
    $bases = collect(\App\Enums\PriceBasis::cases())->mapWithKeys(fn ($b) => [$b->value => $b->label()])->all();
@endphp

<x-layouts.admin :title="$isNew ? 'Hotel baru' : $hotel->name">
    <h1 class="text-4xl">{{ $isNew ? 'Hotel baru' : $hotel->name }}</h1>
    @unless ($isNew)
        <p class="mt-2 flex items-center gap-3"><x-status-badge :status="$hotel->status" /> <span class="typewriter">/hotels/{{ $hotel->slug }}</span></p>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-start">
            <x-admin.readiness :report="$report" />
            <x-admin.status-actions :record="$hotel" type="hotels" />
        </div>
    @endunless

    <form method="POST" action="{{ $isNew ? route('admin.hotels.store') : route('admin.hotels.update', $hotel->id) }}" class="mt-12 space-y-12" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Identitas</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" label="Nama" :value="$hotel->name" required />
                <x-field name="slug" label="Slug URL" :value="$hotel->slug" :hint="$locked ? 'Dikunci karena sudah terbit.' : 'Kosongkan untuk dibuat dari nama.'" :readonly="$locked" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="region_id" label="Wilayah administratif" :options="$regions->pluck('name', 'id')->all()" :selected="$hotel->region_id" placeholder="Pilih wilayah" required />
                <x-select name="type" label="Jenis akomodasi" :options="$types" :selected="$hotel->type?->value" placeholder="Pilih jenis" required />
            </div>
            <x-field name="area_label" label="Nama area populer" :value="$hotel->area_label" hint="Contoh: Ubud atau Seminyak." />
            <x-textarea name="summary" label="Ringkasan" :value="$hotel->summary" :rows="3" hint="Maksimal 500 karakter." required />
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Lokasi</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="lat" label="Lintang (latitude)" inputmode="decimal" :value="$hotel->lat" required />
                <x-field name="lng" label="Bujur (longitude)" inputmode="decimal" :value="$hotel->lng" required />
            </div>
            <x-field name="address" label="Alamat" :value="$hotel->address" />
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Harga per kamar per malam</legend>
            <p class="field-hint">Dalam rupiah penuh, tanpa titik. Kosongkan bila tidak ada sumber. Situs tidak menampilkan ketersediaan kamar.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="price_min" label="Harga terendah" type="number" min="0" :value="$hotel->price_min" />
                <x-field name="price_max" label="Harga tertinggi" type="number" min="0" :value="$hotel->price_max" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="price_basis" label="Jenis sumber harga" :options="$bases" :selected="$hotel->price_basis?->value" placeholder="Pilih jenis" />
                <x-select name="price_status" label="Status verifikasi harga" :options="$verif" :selected="$hotel->price_status?->value ?? 'unknown'" required />
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="font-display text-2xl font-bold">Sumber dan verifikasi</legend>
            <x-field name="source_url" label="Tautan sumber" type="url" :value="$hotel->source_url" hint="Wajib untuk terbit." />
            <div class="max-w-xs"><x-field name="verified_at" label="Tanggal verifikasi" type="date" :value="$hotel->verified_at?->format('Y-m-d')" /></div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-4 border-t border-brass/60 pt-6">
            <x-button type="submit">Simpan draf</x-button>
            <a class="btn btn-link" href="{{ route('admin.hotels.index') }}">Kembali ke daftar</a>
        </div>
    </form>

    @unless ($isNew)
        @if ($hotel->priceSnapshots->isNotEmpty())
            <section aria-labelledby="riwayat" class="mt-16 border-t border-brass/60 pt-10">
                <h2 id="riwayat" class="text-2xl">Riwayat harga</h2>
                <ul class="mt-4 divide-y divide-ink/20 border-y border-ink/20 text-sm">
                    @foreach ($hotel->priceSnapshots as $snapshot)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <span>Rp{{ number_format($snapshot->price_min ?? $snapshot->price_max, 0, ',', '.') }}@if ($snapshot->price_max && $snapshot->price_max !== $snapshot->price_min) sampai Rp{{ number_format($snapshot->price_max, 0, ',', '.') }}@endif, {{ $snapshot->price_basis?->label() }}</span>
                            <span class="typewriter">diperiksa {{ $snapshot->checked_at->translatedFormat('j M Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-16 border-t border-brass/60 pt-10">
            <x-admin.media-panel :owner="$hotel" type="hotel" />
        </div>

        <form method="POST" action="{{ route('admin.hotels.destroy', $hotel->id) }}" class="mt-16 border-t border-brass/60 pt-6" onsubmit="return confirm('Hapus hotel ini?')">
            @csrf
            @method('DELETE')
            <p class="text-sm text-ink-soft">Hotel yang terbit tidak bisa dihapus. Arsipkan dulu.</p>
            <button type="submit" class="btn btn-link text-oxblood">Hapus hotel</button>
        </form>
    @endunless
</x-layouts.admin>

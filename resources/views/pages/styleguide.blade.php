@php
    $palette = [
        ['Archive Paper', '#F6F0E4', 'bg-paper', 'Latar halaman'],
        ['Soft White', '#FAF7F0', 'bg-surface', 'Kartu dan form'],
        ['Ink', '#202723', 'bg-ink', 'Teks utama. Kontras 13,5'],
        ['Ink Soft', '#4B554F', 'bg-ink-soft', 'Teks sekunder. Kontras 6,8'],
        ['Archive Rust', '#9C4935', 'bg-rust', 'CTA dan tautan. Kontras 5,4'],
        ['Rust Deep', '#843A29', 'bg-rust-deep', 'Hover. Kontras 7,1'],
        ['Moss', '#5C6B56', 'bg-moss', 'Status sekunder. Kontras 5,0'],
        ['Oxblood', '#7A2418', 'bg-oxblood', 'Error. Kontras 8,8'],
        ['Brass', '#B18A50', 'bg-brass', 'Garis saja. Kontras 2,8'],
    ];
    $dark = ['bg-ink', 'bg-ink-soft', 'bg-rust', 'bg-rust-deep', 'bg-moss', 'bg-oxblood'];
@endphp

<x-layouts.app title="Styleguide">
    <div class="space-y-16">
        <header class="max-w-2xl">
            <h1 class="text-5xl">Styleguide</h1>
            <p class="mt-3 text-ink-soft">Hanya tampil di environment local. Dipakai untuk meninjau token sebelum halaman fase berikutnya dibangun.</p>
        </header>

        <section aria-labelledby="sg-warna" class="space-y-4">
            <h2 id="sg-warna" class="text-2xl">Warna</h2>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($palette as [$name, $hex, $class, $use])
                    <li class="flex items-stretch gap-4 border border-ink/20 bg-surface">
                        <span class="{{ $class }} w-20 shrink-0 border-r border-ink/20" aria-hidden="true"></span>
                        <span class="py-3 pr-3">
                            <span class="block font-semibold">{{ $name }}</span>
                            <span class="typewriter block">{{ $hex }}</span>
                            <span class="block text-sm text-ink-soft">{{ $use }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="sg-tipe" class="space-y-6">
            <h2 id="sg-tipe" class="text-2xl">Tipografi</h2>
            <div class="space-y-4">
                <p class="font-display text-6xl font-extrabold leading-none tracking-tight">The Dewata Archive</p>
                <h3 class="text-4xl">Judul halaman memakai Playfair Display</h3>
                <p class="max-w-[62ch]">Paragraf memakai Inter dengan jarak baris 1,6 dan lebar baca dibatasi sekitar 62 karakter. Teks panjang tidak pernah memakai huruf mesin tik.</p>
                <p class="typewriter">Catatan kaki, tanggal, dan label rute memakai Courier Prime.</p>
                <p class="meta">Label metadata</p>
            </div>
        </section>

        <section aria-labelledby="sg-tombol" class="space-y-4">
            <h2 id="sg-tombol" class="text-2xl">Tombol</h2>
            <div class="flex flex-wrap items-center gap-4">
                <x-button>Primary</x-button>
                <x-button variant="secondary">Secondary</x-button>
                <x-button variant="link">Tertiary</x-button>
                <x-button disabled>Disabled</x-button>
                <x-button :loading="true">Menyimpan</x-button>
            </div>
            <p class="text-sm text-ink-soft">Hover, focus (tekan Tab), active, disabled, dan loading dirancang untuk semua varian.</p>
        </section>

        <section aria-labelledby="sg-form" class="space-y-4">
            <h2 id="sg-form" class="text-2xl">Form</h2>
            <div class="grid max-w-3xl gap-6 sm:grid-cols-2">
                <x-field name="sg_default" label="Judul arsip" hint="Contoh: Tiga hari di Gianyar" required />
                <x-field name="sg_optional" label="Catatan pribadi" />
                <x-field name="sg_disabled" label="Dinonaktifkan" disabled />
                <x-field name="sg_error" label="Dengan error" error="Isi judul arsip sebelum menyimpan." required />
            </div>
        </section>

        <section aria-labelledby="sg-verif" class="space-y-3">
            <h2 id="sg-verif" class="text-2xl">Status verifikasi</h2>
            <x-verification :status="\App\Enums\VerificationStatus::Verified" :at="now()->subDays(12)" label="Jam buka" />
            <x-verification :status="\App\Enums\VerificationStatus::NeedsReview" :at="now()->subMonths(9)" label="Harga" />
            <x-verification />
        </section>
    </div>
</x-layouts.app>

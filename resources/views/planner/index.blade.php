<x-layouts.app title="Rencanakan perjalanan" description="Susun arsip perjalanan Bali dari rekomendasi atau pilih sendiri setiap tempat. Bisa dimulai tanpa akun.">
    <header class="max-w-[62ch]">
        <h1 class="text-4xl sm:text-5xl">Rencanakan perjalanan</h1>
        <p class="mt-4 text-lg leading-relaxed text-ink-soft">
            Ada dua cara memulai arsip. Keduanya berakhir di editor yang sama, dengan peta dan rute per hari, dan keduanya bisa diubah kapan saja. Tidak perlu akun untuk mencoba.
        </p>
    </header>

    <div class="mt-12 grid border-y-[3px] border-double border-ink md:grid-cols-2">
        <section aria-labelledby="mode-rekomendasi" class="flex flex-col py-8 md:border-r md:border-ink/25 md:pr-10">
            <h2 id="mode-rekomendasi" class="text-3xl">Dapatkan rekomendasi</h2>
            <p class="mt-3 text-ink-soft">Jawab enam pertanyaan singkat, lalu arsip menyusun draf per hari dari tempat yang sudah diverifikasi. Setiap pilihan disertai alasannya.</p>
            <p class="typewriter mt-4">Contoh: tiga hari, tema pura dan kerajinan, sekitar Gianyar, tempo santai.</p>
            <div class="mt-auto pt-8"><x-button :href="route('planner.recommend')">Dapatkan rekomendasi</x-button></div>
        </section>

        <section aria-labelledby="mode-manual" class="flex flex-col border-t border-ink/25 py-8 md:border-t-0 md:pl-10">
            <h2 id="mode-manual" class="text-3xl">Susun sendiri</h2>
            <p class="mt-3 text-ink-soft">Mulai dari arsip kosong. Cari tempat di katalog, tambahkan ke hari yang Anda pilih, dan atur urutannya sendiri. Tidak ada tempat yang disisipkan otomatis.</p>
            <p class="typewriter mt-4">Contoh: Anda sudah punya daftar tempat dan ingin melihat rutenya.</p>
            <div class="mt-auto pt-8"><x-button :href="route('planner.manual')">Susun sendiri</x-button></div>
        </section>
    </div>

    <section aria-labelledby="catatan-planner" class="mt-12 grid gap-8 md:grid-cols-12">
        <div class="md:col-span-7">
            <h2 id="catatan-planner" class="text-2xl">Yang perlu diketahui</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-[0.9375rem]">
                <li>Planner tidak menghitung biaya perjalanan. Harga hanya muncul sebagai kisaran per malam bila Anda mencari penginapan.</li>
                <li>Tanpa akun, draf tersimpan di browser ini selama {{ config('planner.guest_ttl_days') }} hari. Buat akun bila ingin membukanya di perangkat lain.</li>
                <li>Jarak dan waktu tempuh berasal dari Google Maps dan merupakan estimasi, bukan jaminan kondisi jalan.</li>
            </ul>
        </div>

        @if ($recent->isNotEmpty())
            <div class="md:col-span-5">
                <h2 class="text-2xl">{{ auth()->check() ? 'Arsip terakhir Anda' : 'Draf di browser ini' }}</h2>
                <ul class="mt-4 divide-y divide-ink/15 border-y border-ink/15">
                    @foreach ($recent as $plan)
                        <li class="py-3">
                            <a class="font-semibold hover:text-rust" href="{{ \App\Planner\PlanAccess::editorUrl($plan) }}">{{ $plan->title }}</a>
                            <p class="typewriter">{{ $plan->days_count }} hari, diubah {{ $plan->updated_at->translatedFormat('j F Y') }}</p>
                        </li>
                    @endforeach
                </ul>
                @auth
                    <p class="mt-3"><a class="btn btn-link" href="{{ route('account.plans.index') }}">Semua arsip</a></p>
                @endauth
            </div>
        @endif
    </section>
</x-layouts.app>

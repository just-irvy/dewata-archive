<x-layouts.app title="Susun sendiri">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('planner.index') }}">Rencanakan perjalanan</a></nav>
    <h1 class="mt-3 text-4xl sm:text-5xl">Susun sendiri</h1>
    <p class="mt-4 max-w-[62ch] text-lg text-ink-soft">Beri nama arsip dan tentukan jumlah hari. Hari-hari dibuat kosong, lalu Anda memilih tempat dari katalog dan mengatur urutannya.</p>

    <form method="POST" action="{{ route('planner.manual.store') }}" class="mt-10 max-w-xl space-y-6" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <x-field name="title" label="Judul arsip" :required="true" maxlength="120" placeholder="Misalnya: Empat hari di utara" />
        <x-field name="days_count" label="Jumlah hari" type="number" :required="true" min="1" :max="config('planner.max_days')" inputmode="numeric" :value="3" :hint="'Antara 1 dan '.config('planner.max_days').' hari. Hari bisa ditambah atau dihapus nanti.'" />
        <x-field name="start_date" label="Tanggal mulai" type="date" :min="now()->toDateString()" hint="Tanpa tanggal, itinerary memakai Hari 1, Hari 2, dan seterusnya." />
        <x-select name="origin_region_id" label="Wilayah titik awal" :options="$regions->pluck('name', 'id')->all()" placeholder="Belum ditentukan" hint="Dipakai untuk menyaring pilihan penginapan sebagai titik awal rute." />
        <x-select name="transport_mode" label="Transportasi" :options="collect($transports)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" selected="undecided" hint="Menentukan moda rute di peta. Transportasi umum tidak dirutekan." />

        <div class="flex flex-wrap items-center gap-4 pt-2">
            <button type="submit" class="btn btn-primary" x-bind:aria-busy="busy.toString()">Buat arsip kosong</button>
            <a class="btn btn-link" href="{{ route('planner.index') }}">Kembali</a>
        </div>
    </form>
</x-layouts.app>

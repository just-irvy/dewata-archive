<x-layouts.app title="Uji peta">
    <h1 class="text-4xl">Uji peta dan rute</h1>
    <p class="mt-2 max-w-[62ch] text-ink-soft">Halaman ini hanya ada di environment local. Ia memakai tempat terbit sungguhan, urutannya menurut wilayah dan nama, untuk menguji key, Map ID, polyline, dan moda perjalanan.</p>

    <form method="GET" class="mt-8 flex flex-wrap items-end gap-5 border-y border-ink/25 py-5">
        <x-select name="mode" label="Moda" :options="collect($modes)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" :selected="$mode->value" />
        <label class="flex min-h-11 items-center gap-2"><input type="hidden" name="hotel" value="0"><input type="checkbox" name="hotel" value="1" class="size-5 accent-rust" @checked($useHotel)> Mulai dari hotel</label>
        <label class="flex min-h-11 items-center gap-2"><input type="hidden" name="kembali" value="0"><input type="checkbox" name="kembali" value="1" class="size-5 accent-rust" @checked($return)> Kembali ke hotel</label>
        <x-button type="submit" variant="secondary">Terapkan</x-button>
    </form>

    @if ($route === null)
        <p class="mt-8 max-w-[62ch]">Belum cukup data. Butuh minimal {{ $useHotel ? 'satu tempat dan satu hotel terbit' : 'dua tempat terbit' }}.</p>
    @elseif (! \App\Support\MapMarkers::enabled())
        <p class="mt-8 max-w-[62ch] font-semibold">GOOGLE_MAPS_BROWSER_KEY belum diisi di .env, jadi peta tidak dirender.</p>
    @else
        <x-map :markers="$markers" :route="$route" label="Peta uji rute" class="mt-8" />
    @endif

    <ol class="mt-10 max-w-xl divide-y divide-ink/20 border-y border-ink/20">
        @foreach ($places as $i => $place)
            <li id="map-item-{{ $place->id }}" tabindex="-1" class="flex justify-between gap-4 py-3 outline-none focus-visible:outline-2 focus-visible:outline-offset-4">
                <span>D1·{{ $i + 1 }} {{ $place->name }}</span>
                <span class="typewriter">{{ $place->lat }}, {{ $place->lng }}</span>
            </li>
        @endforeach
    </ol>
</x-layouts.app>

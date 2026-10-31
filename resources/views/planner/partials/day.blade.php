@php
    $n = $day->day_number;
    $full = $day->items->count() >= $maxItems;
    $alts = $alternatives[$n] ?? [];
    $hotelOpts = $hotelOptions->mapWithKeys(fn ($h) => [$h->id => $h->name.($h->region ? ', '.$h->region->name : '')])->all();
@endphp

<section id="hari-{{ $n }}" aria-labelledby="judul-hari-{{ $n }}" class="scroll-mt-6 break-inside-avoid">
    <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2 border-b-2 border-ink pb-3">
        <div>
            <p class="typewriter">{{ $day->date ? $day->date->translatedFormat('l, j F Y') : 'Tanpa tanggal' }}</p>
            <h2 id="judul-hari-{{ $n }}" class="mt-1 text-3xl">Hari {{ $n }}@if ($day->title)<span class="font-normal italic">, {{ $day->title }}</span>@endif</h2>
        </div>
        <button type="button" class="btn btn-link print:hidden" x-cloak x-on:click="showDay({{ $n }}, true)" x-show="{{ $mapsEnabled ? 'true' : 'false' }}">Lihat di peta</button>
    </header>

    <p class="mt-3 text-sm text-ink-soft">{{ collect([
        $day->items->count().' tempat',
        $day->hotel ? 'titik awal '.$day->hotel->name.($day->return_to_hotel ? ', kembali ke penginapan' : '') : null,
        $day->region && ! $day->title ? $day->region->name : null,
    ])->filter()->join(', ') }}</p>
    @if ($day->notes)
        <p class="mt-2 whitespace-pre-line text-[0.9375rem]">{{ $day->notes }}</p>
    @endif

    @if ($day->items->isEmpty())
        <div class="mt-5 border-l-2 border-brass/60 pl-4 print:hidden"
             x-on:dragover.prevent x-on:drop.prevent="drop($event, {{ $day->id }}, @js($moveUrl))">
            <p class="font-semibold">Hari ini masih kosong.</p>
            <p class="mt-1 text-sm text-ink-soft">Cari tempat di panel katalog lalu pilih “Hari {{ $n }}”, atau seret tempat dari hari lain ke sini.</p>
        </div>
    @else
        <ol class="mt-5 space-y-0 divide-y divide-ink/15 border-y border-ink/15"
            x-on:dragover.prevent x-on:drop.prevent="drop($event, {{ $day->id }}, @js($moveUrl))">
            @foreach ($day->items as $i => $item)
                @include('planner.partials.item', ['item' => $item, 'index' => $i, 'count' => $day->items->count()])
            @endforeach
        </ol>
    @endif

    <div class="mt-5 flex flex-wrap items-start gap-x-6 gap-y-3 print:hidden">
        <details class="w-full sm:w-auto sm:flex-1">
            <summary class="flex min-h-11 cursor-pointer items-center text-[0.9375rem] font-semibold">Atur hari ini</summary>
            <form method="POST" action="{{ route('planner.plans.days.update', [$plan->id, $day->id]) }}" class="mt-3 space-y-5 border-l-2 border-ink/20 pl-4 pb-2">
                @csrf
                @method('PATCH')
                <x-field name="title" :id="'hari-'.$n.'-judul'" label="Judul hari" maxlength="120" :value="$day->title" />
                <x-select name="hotel_id" :id="'hari-'.$n.'-hotel'" label="Penginapan sebagai titik awal rute" :options="$hotelOpts" :selected="$day->hotel_id" placeholder="Tanpa penginapan" hint="Rute hari ini dimulai dari penginapan ini. Bukan pemesanan." />
                <label class="flex min-h-11 items-center gap-3">
                    <input type="hidden" name="return_to_hotel" value="0">
                    <input type="checkbox" name="return_to_hotel" value="1" class="size-5 accent-rust" @checked($day->return_to_hotel)>
                    <span>Kembali ke penginapan di akhir hari</span>
                </label>
                <div class="field">
                    <label for="hari-{{ $n }}-catatan" class="field-label">Catatan hari <span class="field-optional">(opsional)</span></label>
                    <textarea id="hari-{{ $n }}-catatan" name="notes" rows="3" maxlength="1000" class="field-input">{{ $day->notes }}</textarea>
                </div>
                <button type="submit" class="btn btn-secondary">Simpan hari {{ $n }}</button>
            </form>
        </details>

        @if ($plan->canRecommend())
            <form method="POST" action="{{ route('planner.plans.days.regenerate', [$plan->id, $day->id]) }}" x-data x-on:submit="if (! confirm('Isi Hari {{ $n }} akan diganti dengan susunan baru, termasuk tempat yang Anda tambahkan sendiri. Lanjutkan?')) $event.preventDefault()">
                @csrf
                <button type="submit" class="btn btn-link">Susun ulang hari ini</button>
            </form>
        @endif

        @if ($plan->days->count() > 1)
            <form method="POST" action="{{ route('planner.plans.days.destroy', [$plan->id, $day->id]) }}" x-data x-on:submit="if (! confirm('Hapus Hari {{ $n }} beserta {{ $day->items->count() }} tempat di dalamnya?')) $event.preventDefault()">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-oxblood">Hapus hari {{ $n }}</button>
            </form>
        @endif
    </div>

    @if ($alts !== [])
        <details class="mt-4 border-t border-ink/15 pt-2 print:hidden">
            <summary class="flex min-h-11 cursor-pointer items-center text-[0.9375rem] font-semibold">Cadangan untuk hari ini</summary>
            <ul class="mt-2 space-y-3">
                @foreach ($alts as $alt)
                    <li class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 border-l-2 border-brass/60 pl-3">
                        <div class="min-w-0 flex-1">
                            <a class="font-semibold hover:text-rust" href="{{ route('places.show', $alt['candidate']->slug) }}">{{ $alt['candidate']->name }}</a>
                            <p class="text-sm text-ink-soft">{{ $alt['candidate']->areaLabel ? $alt['candidate']->areaLabel.', ' : '' }}{{ $alt['candidate']->regionName }}. {{ $alt['reason'] }}</p>
                        </div>
                        @unless ($full)
                            <form method="POST" action="{{ route('planner.plans.items.store', $plan->id) }}">
                                @csrf
                                <input type="hidden" name="day_id" value="{{ $day->id }}">
                                <input type="hidden" name="place_id" value="{{ $alt['candidate']->id }}">
                                <button type="submit" class="btn btn-link">Tambah ke hari {{ $n }}</button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</section>

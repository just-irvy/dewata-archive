{{-- Tambah tempat dari katalog terbit (mode Manual dan penambahan di mode Rekomendasi). --}}
@php
    $dayOptions = $plan->days->mapWithKeys(fn ($d) => [$d->id => 'Hari '.$d->day_number.($d->items->count() >= $maxItems ? ' (penuh)' : '')])->all();
    $activeDayId = $plan->days->firstWhere('day_number', $activeDay)?->id;
@endphp

<section id="tambah-tempat" aria-labelledby="judul-tambah" class="scroll-mt-6">
    <h2 id="judul-tambah" class="text-2xl">Tambah tempat</h2>
    <p class="mt-2 text-sm text-ink-soft">Hanya tempat terbit di katalog yang bisa ditambahkan, agar lokasi dan sumbernya bisa dipercaya.</p>

    <form method="GET" action="{{ $editorUrl }}#tambah-tempat" class="mt-4 space-y-4" role="search">
        <input type="hidden" name="hari" value="{{ $activeDay }}">
        <x-field name="cari" label="Cari nama tempat" type="search" maxlength="100" :value="$search['q'] ?? ''" placeholder="Misalnya: pura, sawah, pantai" />
        <x-select name="wilayah" label="Wilayah" :options="$searchRegions" :selected="$search['region'] ?? ''" placeholder="Semua wilayah" />
        <button type="submit" class="btn btn-secondary">Cari di katalog</button>
    </form>

    @if ($search !== null)
        <div class="mt-6" aria-live="polite">
            @if ($search['results']->isEmpty())
                <p class="font-semibold">Tidak ada tempat terbit yang cocok.</p>
                <p class="mt-1 text-sm text-ink-soft">Coba kata kunci lain atau hapus pilihan wilayah.</p>
            @else
                <p class="typewriter">{{ $search['results']->count() }}{{ $search['more'] ? '+' : '' }} hasil</p>
                <ul class="mt-2 divide-y divide-ink/15 border-y border-ink/15">
                    @foreach ($search['results'] as $result)
                        @php($inDay = $search['inPlan'][$result->id] ?? null)
                        <li class="py-3">
                            <a class="font-semibold hover:text-rust" href="{{ route('places.show', $result->slug) }}">{{ $result->name }}</a>
                            <p class="typewriter">{{ $result->area_label ? $result->area_label.', ' : '' }}{{ $result->region?->name }}</p>
                            @if ($inDay)
                                <p class="mt-1 text-sm text-ink-soft">Sudah ada di Hari {{ $inDay }}.</p>
                            @else
                                <form method="POST" action="{{ route('planner.plans.items.store', $plan->id) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="place_id" value="{{ $result->id }}">
                                    <input type="hidden" name="cari" value="{{ $search['q'] }}">
                                    <input type="hidden" name="wilayah" value="{{ $search['region'] }}">
                                    <label for="tambah-{{ $result->id }}" class="sr-only">Hari untuk {{ $result->name }}</label>
                                    <select id="tambah-{{ $result->id }}" name="day_id" class="field-input w-auto min-h-10 py-1 text-sm">
                                        @foreach ($dayOptions as $id => $text)
                                            <option value="{{ $id }}" @selected($id === $activeDayId)>{{ $text }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary min-h-10 px-3 text-sm">Tambahkan</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($search['more'])
                    <p class="mt-2 text-sm text-ink-soft">Ada hasil lain. Perjelas kata kunci atau pilih wilayah.</p>
                @endif
            @endif
        </div>
    @endif
</section>

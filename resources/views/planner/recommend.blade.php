@php
    $source = session()->hasOldInput() ? session()->getOldInput() : $prefill;
    $strs = fn ($v) => array_values(array_map('strval', array_filter((array) $v, fn ($x) => $x !== null && $x !== '')));
    $values = [
        'days_count' => (string) ($source['days_count'] ?? 3),
        'start_date' => (string) ($source['start_date'] ?? ''),
        'origin_region_id' => (string) ($source['origin_region_id'] ?? ''),
        'party' => (string) ($source['party'] ?? ''),
        'themes' => $strs($source['themes'] ?? []),
        'surprise' => (bool) ($source['surprise'] ?? false),
        'region_ids' => $strs($source['region_ids'] ?? []),
        'flexibility' => (string) ($source['flexibility'] ?? 'nearby'),
        'pace' => (string) ($source['pace'] ?? 'balanced'),
        'mobility' => $strs($source['mobility'] ?? []),
        'transport_mode' => (string) ($source['transport_mode'] ?? 'undecided'),
        'daily_time' => (string) ($source['daily_time'] ?? 'flexible'),
        'avoid_outdoor' => (bool) ($source['avoid_outdoor'] ?? false),
        'want_hotel' => (bool) ($source['want_hotel'] ?? false),
        'stay_region_ids' => $strs($source['stay_region_ids'] ?? []),
        'price_min' => (string) ($source['price_min'] ?? ''),
        'price_max' => (string) ($source['price_max'] ?? ''),
        'hotel_types' => $strs($source['hotel_types'] ?? []),
        'guests' => (string) ($source['guests'] ?? ''),
        'rooms' => (string) ($source['rooms'] ?? ''),
        'title' => (string) ($source['title'] ?? ''),
    ];
    $allRegions = $regions->pluck('name', 'id')->union($hotelRegions->pluck('name', 'id'))->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
    $labels = [
        'regions' => $allRegions,
        'themes' => $themes->pluck('name', 'slug')->all(),
        'party' => config('planner.labels.party'),
        'flexibility' => config('planner.labels.flexibility'),
        'dailyTime' => config('planner.labels.daily_time'),
        'pace' => collect($paces)->mapWithKeys(fn ($p) => [$p->value => 'tempo '.mb_strtolower($p->label())])->all(),
        'transport' => collect($transports)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
        'access' => $access,
        'hotelTypes' => $hotelTypes,
    ];
    $limits = ['maxDays' => config('planner.max_days'), 'maxThemes' => config('planner.max_themes')];
    $groups = [1 => 'Kerangka perjalanan', 2 => 'Minat', 3 => 'Wilayah', 4 => 'Tempo dan batasan', 5 => 'Penginapan'];
@endphp

<x-layouts.app title="Dapatkan rekomendasi">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('planner.index') }}">Rencanakan perjalanan</a></nav>
    <h1 class="mt-3 text-4xl sm:text-5xl">Dapatkan rekomendasi</h1>
    <p class="mt-4 max-w-[62ch] text-ink-soft">Enam langkah singkat. Jawaban tidak hilang saat Anda kembali ke langkah sebelumnya, dan setiap kelompok bisa diubah dari halaman tinjau.</p>

    <form
        method="POST"
        action="{{ route('planner.generate') }}"
        class="mt-10 grid gap-10 lg:grid-cols-12"
        x-data="plannerWizard(@js(['initialStep' => $initialStep, 'values' => $values, 'labels' => $labels, 'limits' => $limits]))"
        x-on:submit="submit($event)"
        novalidate
    >
        @csrf

        {{-- Penanda kemajuan. Langkah yang sudah lewat bisa dibuka lagi. --}}
        <nav aria-label="Langkah wizard" class="lg:col-span-3" x-cloak>
            <p class="typewriter" aria-live="polite">Langkah <span x-text="step"></span> dari 6</p>
            <ol class="mt-3 border-l-2 border-ink/20">
                <template x-for="item in steps" x-bind:key="item.n">
                    <li>
                        <button
                            type="button"
                            class="-ml-0.5 flex min-h-11 w-full items-center gap-3 border-l-2 pl-4 text-left text-[0.9375rem]"
                            x-bind:class="item.n === step ? 'border-rust font-semibold text-ink' : (item.n < step ? 'border-transparent text-ink hover:text-rust' : 'border-transparent text-ink-soft')"
                            x-bind:aria-current="item.n === step ? 'step' : null"
                            x-bind:disabled="item.n > step"
                            x-on:click="go(item.n)"
                        >
                            <span class="typewriter w-4 text-ink-soft" x-text="item.n"></span>
                            <span x-text="item.title"></span>
                        </button>
                    </li>
                </template>
            </ol>
        </nav>

        <div class="lg:col-span-9 lg:max-w-2xl">
            @if ($errors->any())
                <div role="alert" class="mb-8 border-l-4 border-oxblood bg-surface px-4 py-3">
                    <p class="font-semibold text-oxblood">Periksa kembali jawaban berikut.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div x-show="errors.length > 0" x-cloak x-ref="errors" tabindex="-1" role="alert" class="mb-8 border-l-4 border-oxblood bg-surface px-4 py-3 outline-none">
                <template x-for="(message, i) in errors" x-bind:key="i"><p class="text-sm font-semibold text-oxblood" x-text="message"></p></template>
            </div>

            {{-- Langkah 1 --}}
            <section x-show="step === 1" aria-labelledby="langkah-1" class="space-y-6">
                <h2 id="langkah-1" x-ref="step1" tabindex="-1" class="text-3xl outline-none">Kerangka perjalanan</h2>
                <x-field name="days_count" label="Jumlah hari" type="number" :required="true" min="1" :max="config('planner.max_days')" inputmode="numeric" x-model="values.days_count" :value="$values['days_count']" :hint="'Antara 1 dan '.config('planner.max_days').' hari. Bisa diubah setelah draf dibuat.'" />
                <x-field name="start_date" label="Tanggal mulai" type="date" :min="now()->toDateString()" x-model="values.start_date" :value="$values['start_date']" hint="Tanpa tanggal, itinerary memakai Hari 1, Hari 2, dan seterusnya. Jam buka tidak dikarang." />
                <x-select name="origin_region_id" label="Titik awal atau area menginap" :options="$regions->pluck('name', 'id')->all()" :selected="$values['origin_region_id']" placeholder="Belum tahu" x-model="values.origin_region_id" hint="Dipakai agar rute tidak bolak-balik. Bukan anggaran." />
                <x-select name="party" label="Rombongan" :options="config('planner.labels.party')" :selected="$values['party']" placeholder="Tidak disebutkan" x-model="values.party" hint="Hanya dipakai bila data tempat mendukung. Saat ini belum memengaruhi hasil." />
            </section>

            {{-- Langkah 2 --}}
            <section x-show="step === 2" aria-labelledby="langkah-2" class="space-y-6">
                <h2 id="langkah-2" x-ref="step2" tabindex="-1" class="text-3xl outline-none">Minat</h2>
                <fieldset class="field" x-bind:disabled="values.surprise">
                    <legend class="field-label">Tema utama, paling banyak {{ config('planner.max_themes') }}</legend>
                    <p class="field-hint">Hanya tema yang punya tempat terbit di arsip yang ditampilkan.</p>
                    <div class="mt-2 grid gap-x-8 sm:grid-cols-2">
                        @forelse ($themes as $theme)
                            <label class="flex min-h-11 items-start gap-3 py-1.5">
                                <input type="checkbox" name="themes[]" value="{{ $theme->slug }}" class="mt-1 size-5 shrink-0 accent-rust" x-model="values.themes" @checked(in_array($theme->slug, $values['themes'], true))>
                                <span>
                                    <span class="font-semibold">{{ $theme->name }}</span>
                                    @if ($theme->description)
                                        <span class="block text-sm text-ink-soft">{{ $theme->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-ink-soft">Belum ada tema dengan tempat terbit. Pilih "Kejutkan saya".</p>
                        @endforelse
                    </div>
                </fieldset>
                <label class="flex min-h-11 items-start gap-3 border-t border-ink/20 pt-4">
                    <input type="hidden" name="surprise" value="0">
                    <input type="checkbox" name="surprise" value="1" class="mt-1 size-5 shrink-0 accent-rust" x-model="values.surprise" @checked($values['surprise'])>
                    <span><span class="font-semibold">Kejutkan saya</span><span class="block text-sm text-ink-soft">Tema diabaikan. Hasil tetap dibatasi durasi, wilayah, dan kebutuhan mobilitas.</span></span>
                </label>
            </section>

            {{-- Langkah 3 --}}
            <section x-show="step === 3" aria-labelledby="langkah-3" class="space-y-6">
                <h2 id="langkah-3" x-ref="step3" tabindex="-1" class="text-3xl outline-none">Wilayah</h2>
                <fieldset class="field">
                    <legend class="field-label">Wilayah yang ingin dikunjungi</legend>
                    <p class="field-hint">Batas kabupaten dan kota. Kosongkan untuk seluruh Bali. Lebih banyak wilayah berarti lebih banyak waktu di jalan.</p>
                    <div class="mt-2 grid gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($regions as $region)
                            <label class="flex min-h-11 items-center gap-3">
                                <input type="checkbox" name="region_ids[]" value="{{ $region->id }}" class="size-5 accent-rust" x-model="values.region_ids" @checked(in_array((string) $region->id, $values['region_ids'], true))>
                                <span>{{ $region->name }} <span class="typewriter">{{ $region->places_count }} tempat</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="field">
                    <legend class="field-label">Seberapa jauh dalam satu hari</legend>
                    @foreach (config('planner.labels.flexibility') as $value => $label)
                        <label class="flex min-h-11 items-center gap-3">
                            <input type="radio" name="flexibility" value="{{ $value }}" class="size-5 accent-rust" x-model="values.flexibility" @checked($values['flexibility'] === $value)>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </fieldset>
            </section>

            {{-- Langkah 4 --}}
            <section x-show="step === 4" aria-labelledby="langkah-4" class="space-y-6">
                <h2 id="langkah-4" x-ref="step4" tabindex="-1" class="text-3xl outline-none">Tempo dan batasan</h2>
                <fieldset class="field">
                    <legend class="field-label">Tempo</legend>
                    @foreach ($paces as $pace)
                        <label class="flex min-h-11 items-start gap-3 py-1">
                            <input type="radio" name="pace" value="{{ $pace->value }}" class="mt-1 size-5 shrink-0 accent-rust" x-model="values.pace" @checked($values['pace'] === $pace->value)>
                            <span><span class="font-semibold">{{ $pace->label() }}</span> <span class="text-sm text-ink-soft">{{ $pace->hint() }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
                <x-select name="transport_mode" label="Transportasi" :required="true" :options="$labels['transport']" :selected="$values['transport_mode']" x-model="values.transport_mode" hint="Menentukan moda rute di peta. Transportasi umum tidak dirutekan." />
                <fieldset class="field">
                    <legend class="field-label">Waktu harian</legend>
                    @foreach (config('planner.labels.daily_time') as $value => $label)
                        <label class="flex min-h-11 items-center gap-3">
                            <input type="radio" name="daily_time" value="{{ $value }}" class="size-5 accent-rust" x-model="values.daily_time" @checked($values['daily_time'] === $value)>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    <p class="field-hint">Mengatur kepadatan hari. Bukan pengganti jam buka terkini.</p>
                </fieldset>
                @if ($access !== [])
                    <fieldset class="field">
                        <legend class="field-label">Kebutuhan mobilitas <span class="field-optional">(opsional)</span></legend>
                        <p class="field-hint">Hanya tempat dengan data aksesibilitas terverifikasi yang dipilih bila opsi ini dicentang.</p>
                        @foreach ($access as $key => $label)
                            <label class="flex min-h-11 items-center gap-3">
                                <input type="checkbox" name="mobility[]" value="{{ $key }}" class="size-5 accent-rust" x-model="values.mobility" @checked(in_array($key, $values['mobility'], true))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                @endif
                <label class="flex min-h-11 items-start gap-3">
                    <input type="hidden" name="avoid_outdoor" value="0">
                    <input type="checkbox" name="avoid_outdoor" value="1" class="mt-1 size-5 shrink-0 accent-rust" x-model="values.avoid_outdoor" @checked($values['avoid_outdoor'])>
                    <span><span class="font-semibold">Kurangi aktivitas luar ruang</span><span class="block text-sm text-ink-soft">Tempat bertema alam dan pantai diturunkan peringkatnya, tidak dibuang.</span></span>
                </label>
            </section>

            {{-- Langkah 5: satu-satunya input uang --}}
            <section x-show="step === 5" aria-labelledby="langkah-5" class="space-y-6">
                <h2 id="langkah-5" x-ref="step5" tabindex="-1" class="text-3xl outline-none">Penginapan</h2>
                <p class="text-ink-soft">Opsional. Harga di sini hanya kisaran per kamar per malam untuk mencocokkan penginapan. Planner tidak menghitung biaya perjalanan, dan situs ini tidak menampilkan ketersediaan kamar.</p>
                <label class="flex min-h-11 items-center gap-3">
                    <input type="hidden" name="want_hotel" value="0">
                    <input type="checkbox" name="want_hotel" value="1" class="size-5 accent-rust" x-model="values.want_hotel" @checked($values['want_hotel'])>
                    <span class="font-semibold">Tampilkan rekomendasi penginapan</span>
                </label>

                <div x-show="values.want_hotel" class="space-y-6 border-l-2 border-brass/60 pl-5">
                    @if ($hotelRegions->isNotEmpty())
                        <fieldset class="field">
                            <legend class="field-label">Wilayah menginap <span class="field-optional">(opsional)</span></legend>
                            <p class="field-hint">Kosongkan agar mengikuti wilayah itinerary.</p>
                            <div class="mt-1 grid gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($hotelRegions as $region)
                                    <label class="flex min-h-11 items-center gap-3">
                                        <input type="checkbox" name="stay_region_ids[]" value="{{ $region->id }}" class="size-5 accent-rust" x-model="values.stay_region_ids" @checked(in_array((string) $region->id, $values['stay_region_ids'], true))>
                                        <span>{{ $region->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    <div class="grid gap-6 sm:grid-cols-2">
                        <x-field name="price_min" label="Harga minimal per malam (Rp)" type="number" min="0" step="50000" inputmode="numeric" x-model="values.price_min" :value="$values['price_min']" />
                        <x-field name="price_max" label="Harga maksimal per malam (Rp)" type="number" min="0" step="50000" inputmode="numeric" x-model="values.price_max" :value="$values['price_max']" />
                    </div>
                    @if ($hotelTypes !== [])
                        <fieldset class="field">
                            <legend class="field-label">Jenis penginapan <span class="field-optional">(opsional)</span></legend>
                            <div class="mt-1 grid gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($hotelTypes as $value => $label)
                                    <label class="flex min-h-11 items-center gap-3">
                                        <input type="checkbox" name="hotel_types[]" value="{{ $value }}" class="size-5 accent-rust" x-model="values.hotel_types" @checked(in_array($value, $values['hotel_types'], true))>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    <div class="grid gap-6 sm:grid-cols-2">
                        <x-field name="guests" label="Jumlah tamu" type="number" min="1" max="30" inputmode="numeric" x-model="values.guests" :value="$values['guests']" />
                        <x-field name="rooms" label="Jumlah kamar" type="number" min="1" max="15" inputmode="numeric" x-model="values.rooms" :value="$values['rooms']" />
                    </div>
                    <p class="field-hint">Tamu dan kamar dicatat untuk Anda sendiri. Ketersediaan kamar tidak disimpulkan.</p>
                </div>
            </section>

            {{-- Langkah 6 --}}
            <section x-show="step === 6" aria-labelledby="langkah-6" class="space-y-6">
                <h2 id="langkah-6" x-ref="step6" tabindex="-1" class="text-3xl outline-none">Tinjau</h2>

                <dl class="divide-y divide-ink/15 border-y-2 border-ink" x-cloak>
                    @foreach ($groups as $n => $name)
                        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-1 py-4">
                            <div class="min-w-0 flex-1">
                                <dt class="font-semibold">{{ $name }}</dt>
                                <dd class="mt-1 text-ink-soft" x-text="summary({{ $n }})"></dd>
                            </div>
                            <button type="button" class="btn btn-link" x-on:click="go({{ $n }})">Ubah <span class="sr-only">{{ mb_strtolower($name) }}</span></button>
                        </div>
                    @endforeach
                </dl>

                <div x-show="conflicts.length > 0" x-cloak class="border-l-4 border-brass bg-surface px-4 py-3">
                    <p class="font-semibold">Perhatikan sebelum menyusun</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        <template x-for="(warning, i) in conflicts" x-bind:key="i"><li x-text="warning"></li></template>
                    </ul>
                    <p class="mt-2 text-sm text-ink-soft">Anda boleh melanjutkan, atau ubah pilihan di atas.</p>
                </div>

                <x-field name="title" label="Judul arsip" maxlength="120" x-model="values.title" :value="$values['title']" hint="Kosongkan untuk judul otomatis, misalnya “3 hari di Bali, Gianyar”." />

                <p class="text-sm text-ink-soft">Draf disusun dari aturan dan data arsip, bukan AI. Hasilnya bisa diubah, dipindah, atau dihapus.</p>
                <button type="submit" class="btn btn-primary" x-bind:aria-busy="busy.toString()">Susun draf itinerary</button>
            </section>

            <div class="mt-10 flex items-center justify-between gap-4 border-t border-ink/20 pt-6" x-cloak x-show="step < 6">
                <button type="button" class="btn btn-secondary" x-on:click="back()" x-bind:disabled="step === 1">Kembali</button>
                <button type="button" class="btn btn-primary" x-on:click="next()">Lanjut</button>
            </div>
            <div class="mt-6" x-cloak x-show="step === 6">
                <button type="button" class="btn btn-secondary" x-on:click="back()">Kembali</button>
            </div>
        </div>
    </form>
</x-layouts.app>

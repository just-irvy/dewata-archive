@php
    $isGuest = $plan->user_id === null;
    $meta = collect([
        $plan->days_count.' hari',
        $plan->start_date ? 'mulai '.$plan->start_date->translatedFormat('j F Y') : null,
        $plan->pace?->label() ? 'tempo '.mb_strtolower($plan->pace->label()) : null,
        $plan->transport_mode ? 'transportasi '.mb_strtolower($plan->transport_mode->label()) : null,
    ])->filter()->join(', ');
    $firstMap = $mapDays[$activeDay] ?? ['markers' => [], 'route' => null];
    $moveUrl = route('planner.plans.items.move', ['plan' => $plan->id, 'item' => '__ITEM__']);
@endphp

<x-layouts.app :title="$plan->title">
    <div
        x-data="plannerEditor(@js(['activeDay' => $activeDay, 'days' => $mapDays]))"
        class="space-y-10"
    >
        <header class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <nav aria-label="Jejak halaman" class="print:hidden">
                    <ol class="typewriter flex flex-wrap gap-x-2">
                        <li><a class="underline underline-offset-4" href="{{ route('planner.index') }}">Rencanakan perjalanan</a></li>
                        @unless ($isGuest)
                            <li aria-hidden="true">/</li>
                            <li><a class="underline underline-offset-4" href="{{ route('account.plans.index') }}">Arsip saya</a></li>
                        @endunless
                    </ol>
                </nav>
                <p class="typewriter mt-4">{{ $plan->creation_mode->label() }}@if ($plan->generated_at), {{ $plan->generated_at->translatedFormat('j F Y') }}@endif</p>
                <h1 class="mt-1 text-4xl sm:text-5xl">{{ $plan->title }}</h1>
                <p class="mt-3 text-ink-soft">{{ $meta }}</p>
            </div>

            <div class="space-y-3 lg:col-span-4 lg:border-l lg:border-ink/20 lg:pl-6 print:hidden">
                @if ($isGuest)
                    <p class="text-sm"><span class="font-semibold">Draf tamu.</span> Tersimpan di browser ini sampai {{ $plan->expires_at?->translatedFormat('j F Y') }}. Simpan ke akun agar bisa dibuka di perangkat lain.</p>
                    <form method="POST" action="{{ route('planner.plans.save', $plan->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary w-full">Simpan ke akun</button>
                    </form>
                @else
                    <p class="text-sm"><span class="font-semibold">{{ $plan->status->label() }}</span> di akun Anda. Hanya Anda yang bisa membuka arsip ini.</p>
                    @if ($plan->status === \App\Enums\TripPlanStatus::Draft)
                        <form method="POST" action="{{ route('planner.plans.save', $plan->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full">Tandai tersimpan</button>
                        </form>
                    @endif
                @endif
                <div class="flex flex-wrap gap-x-4">
                    <a class="btn btn-link lg:hidden" href="#tambah-tempat">Tambah tempat</a>
                    <button type="button" class="btn btn-link" x-on:click="window.print()">Cetak</button>
                    <button type="button" class="btn btn-link" x-on:click="copySummary()"><span x-text="copied ? 'Ringkasan tersalin' : 'Salin ringkasan'">Salin ringkasan</span></button>
                    @if ($plan->canRecommend())
                        <a class="btn btn-link" href="{{ route('planner.recommend', ['dari' => $plan->id]) }}">Ubah preferensi</a>
                    @endif
                </div>
                <textarea x-ref="summary" hidden readonly class="field-input h-40 text-sm" aria-label="Ringkasan teks">{{ $summary }}</textarea>
            </div>
        </header>

        @if ($errors->any())
            <div role="alert" class="border-l-4 border-oxblood bg-surface px-4 py-3 print:hidden">
                <p class="font-semibold text-oxblood">Perubahan tidak disimpan.</p>
                <ul class="mt-1 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($plan->generation_notes))
            <aside aria-labelledby="catatan-penyusunan" class="border-l-4 border-brass bg-surface px-5 py-4">
                <h2 id="catatan-penyusunan" class="font-body text-base font-semibold">Catatan penyusunan</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($plan->generation_notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </aside>
        @endif

        <details class="border-y border-ink/20 py-3 print:hidden">
            <summary class="flex min-h-11 cursor-pointer items-center font-semibold">Detail arsip</summary>
            <form method="POST" action="{{ route('planner.plans.update', $plan->id) }}" class="mt-4 grid gap-6 pb-4 sm:grid-cols-3">
                @csrf
                @method('PATCH')
                <x-field name="title" label="Judul arsip" :required="true" maxlength="120" :value="$plan->title" />
                <x-field name="start_date" label="Tanggal mulai" type="date" :value="$plan->start_date?->toDateString()" />
                <x-select name="transport_mode" label="Transportasi" :options="collect($transports)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" :selected="$plan->transport_mode?->value" placeholder="Belum ditentukan" />
                <div class="flex flex-wrap items-center gap-4 sm:col-span-3">
                    <button type="submit" class="btn btn-secondary">Simpan detail</button>
                </div>
            </form>
            <form method="POST" action="{{ route('planner.plans.destroy', $plan->id) }}" class="border-t border-ink/15 pt-3" x-data x-on:submit="if (! confirm('Hapus arsip ini beserta semua harinya?')) $event.preventDefault()">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-oxblood">Hapus arsip</button>
            </form>
        </details>

        <div class="grid gap-10 lg:grid-cols-12">
            {{-- Timeline: representasi utama. Peta hanya tampilan kedua dari urutan yang sama. --}}
            <div class="space-y-14 lg:col-span-7" x-data="plannerSortable(@js(csrf_token()))">
                @foreach ($plan->days as $day)
                    @include('planner.partials.day', ['day' => $day])
                @endforeach

                <form method="POST" action="{{ route('planner.plans.days.store', $plan->id) }}" class="border-t-2 border-dashed border-ink/30 pt-6 print:hidden">
                    @csrf
                    <button type="submit" class="btn btn-secondary" @disabled($plan->days->count() >= $maxDays)>Tambah hari</button>
                    <span class="ml-2 text-sm text-ink-soft">Paling banyak {{ $maxDays }} hari.</span>
                </form>
            </div>

            <div class="lg:col-span-5 print:hidden">
                <div class="space-y-10 lg:sticky lg:top-6">
                    <section id="panel-peta" aria-labelledby="judul-peta" class="scroll-mt-6">
                        <h2 id="judul-peta" class="text-2xl">Peta dan rute</h2>
                        @if ($mapsEnabled)
                        <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Pilih hari untuk peta" x-cloak>
                            @foreach ($plan->days as $day)
                                <button
                                    type="button"
                                    class="min-h-11 min-w-11 border-[1.5px] border-ink px-3 text-sm font-semibold"
                                    x-bind:class="day === {{ $day->day_number }} ? 'bg-ink text-paper' : 'hover:bg-surface'"
                                    x-bind:aria-pressed="(day === {{ $day->day_number }}).toString()"
                                    x-on:click="showDay({{ $day->day_number }})"
                                >Hari {{ $day->day_number }}</button>
                            @endforeach
                        </div>
                        @endif

                        @if ($mapsEnabled)
                            <x-map
                                :markers="$firstMap['markers']"
                                :route="$firstMap['route']"
                                :swappable="true"
                                label="Peta itinerary per hari"
                                height="h-80 lg:h-[24rem]"
                                class="mt-4"
                            />
                            <p class="mt-3 text-sm font-semibold" x-show="routeNote" x-text="routeNote" role="status">{{ $firstMap['routeNote'] ?? '' }}</p>
                            <p class="typewriter mt-3">Marker D1·1 berarti Hari 1, tempat pertama. H adalah penginapan titik awal.</p>
                        @else
                            <p class="mt-4 border-l-4 border-brass bg-surface px-4 py-3 text-sm">Peta belum aktif di situs ini. Urutan tempat di daftar tetap menjadi rencana Anda, dan setiap tempat punya tautan Google Maps.</p>
                        @endif
                    </section>

                    @include('planner.partials.search')
                </div>
            </div>
        </div>

        @if ($stayMatches->isNotEmpty() || ($plan->planner_answers['want_hotel'] ?? false))
            @include('planner.partials.stay')
        @endif
    </div>
</x-layouts.app>

@php
    $related ??= collect();
    $collections ??= collect();
    $saved ??= [];
    $media = $preview ? $place->media : $place->media->filter->isPublishable();
    $hero = $media->first();
    $verifiedHours = $place->opening_hours_status === \App\Enums\VerificationStatus::Verified && $place->opening_hours;
    $verifiedAccess = $place->accessibility_status === \App\Enums\VerificationStatus::Verified && $place->accessibility;
    $triLabel = ['yes' => 'Ya', 'no' => 'Tidak'];
@endphp

<x-layouts.app :title="$place->name" :description="$place->summary">
    @if ($preview)
        <p class="typewriter mb-8 border-2 border-dashed border-rust px-3 py-2">
            Pratinjau. Status saat ini: {{ $place->status->label() }}. Halaman ini tidak terlihat publik sampai diterbitkan.
        </p>
    @endif

    <article class="grid gap-10 md:grid-cols-12">
        <div class="md:col-span-8">
            <nav aria-label="Jejak halaman">
                <ol class="typewriter flex flex-wrap gap-x-2">
                    <li><a class="underline underline-offset-4" href="{{ route('places.index') }}">Jelajah</a></li>
                    @if ($place->region)
                        <li aria-hidden="true">/</li>
                        <li><a class="underline underline-offset-4" href="{{ route('regions.show', $place->region->slug) }}">{{ $place->region->name }}</a></li>
                    @endif
                </ol>
            </nav>

            @if ($place->area_label || $place->categories->isNotEmpty())
                <p class="typewriter mt-4">{{ collect([$place->area_label, $place->categories->pluck('name')->join(', ')])->filter()->join(', ') }}</p>
            @endif
            <h1 class="mt-2 text-4xl sm:text-5xl">{{ $place->name }}</h1>
            <p class="mt-5 max-w-[62ch] text-xl leading-relaxed text-ink-soft">{{ $place->summary }}</p>
            @unless ($preview)
                <div class="mt-3"><x-bookmark-button :place="$place" :saved="in_array($place->id, $saved, true)" /></div>
            @endunless

            @if ($hero)
                <figure class="mt-8">
                    <img src="{{ $hero->url() }}" alt="{{ $hero->alt_text }}" @if ($hero->width) width="{{ $hero->width }}" height="{{ $hero->height }}" @endif class="h-auto w-full">
                    @if ($hero->caption || $hero->credit)
                        <figcaption class="typewriter mt-2">{{ $hero->caption }}@if ($hero->credit) Foto: {{ $hero->credit }}@endif</figcaption>
                    @endif
                </figure>
            @endif

            @if (filled($place->body))
                <div class="mt-8 max-w-[62ch] space-y-5 text-[1.0625rem] leading-[1.75]">
                    @foreach (preg_split('/\R{2,}/', trim($place->body)) as $paragraph)
                        <p class="whitespace-pre-line">{{ $paragraph }}</p>
                    @endforeach
                </div>
            @endif

            @if (\App\Support\MapMarkers::enabled())
                <section aria-labelledby="lokasi" class="mt-10">
                    <h2 id="lokasi" class="text-2xl">Lokasi</h2>
                    <x-map :markers="\App\Support\MapMarkers::forPlaces([$place])" :label="'Peta lokasi '.$place->name" class="mt-4" />
                </section>
            @endif

            @if ($media->count() > 1)
                <ul class="mt-10 grid gap-4 sm:grid-cols-2">
                    @foreach ($media->skip(1) as $extra)
                        <li>
                            <figure>
                                <img src="{{ $extra->url() }}" alt="{{ $extra->alt_text }}" @if ($extra->width) width="{{ $extra->width }}" height="{{ $extra->height }}" @endif class="h-auto w-full" loading="lazy">
                                @if ($extra->caption || $extra->credit)
                                    <figcaption class="typewriter mt-2">{{ $extra->caption }}@if ($extra->credit) Foto: {{ $extra->credit }}@endif</figcaption>
                                @endif
                            </figure>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="md:col-span-4" aria-labelledby="perlu-diketahui">
            <div class="border-t-2 border-ink pt-4">
                <h2 id="perlu-diketahui" class="text-xl">Yang perlu diketahui</h2>

                <dl class="mt-4 space-y-5 text-[0.9375rem]">
                    @if ($place->address)
                        <div><dt class="font-semibold">Alamat</dt><dd>{{ $place->address }}</dd></div>
                    @endif

                    @if ($place->typical_visit_minutes)
                        <div><dt class="font-semibold">Perkiraan durasi kunjungan</dt><dd>{{ $place->typical_visit_minutes }} menit</dd></div>
                    @endif

                    @if ($place->price_info)
                        <div><dt class="font-semibold">Tiket</dt><dd>{{ $place->price_info }}</dd></div>
                    @endif

                    <div>
                        <dt class="font-semibold">Jam buka</dt>
                        <dd>
                            @if ($verifiedHours)
                                <ul class="mt-1 space-y-0.5">
                                    @foreach (config('archive.weekdays') as $key => $label)
                                        @php($day = $place->opening_hours[$key] ?? null)
                                        <li class="flex justify-between gap-4"><span>{{ $label }}</span><span class="typewriter text-ink">{{ ! $day ? 'tidak ada data' : (($day['closed'] ?? false) ? 'libur' : $day['open'].' sampai '.$day['close']) }}</span></li>
                                    @endforeach
                                </ul>
                            @else
                                Cek jam buka sebelum berkunjung.
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="font-semibold">Aksesibilitas</dt>
                        <dd>
                            @if ($verifiedAccess)
                                <ul class="mt-1 space-y-0.5">
                                    @foreach (config('archive.accessibility_attributes') as $key => $label)
                                        @if (isset($place->accessibility[$key]))
                                            <li class="flex justify-between gap-4"><span>{{ $label }}</span><span>{{ $triLabel[$place->accessibility[$key]] }}</span></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @else
                                Belum ada data aksesibilitas yang terverifikasi.
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="font-semibold">Peta</dt>
                        <dd><a class="btn btn-link" rel="noopener noreferrer" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ $place->lat }},{{ $place->lng }}">Buka di Google Maps</a></dd>
                    </div>
                </dl>
            </div>

            <div class="mt-8 space-y-2 border-t border-brass/60 pt-4">
                <x-verification :status="$place->verification_status" :at="$place->verified_at" label="Verifikasi tempat" />
                @if ($place->source_url)
                    <p class="text-sm"><a class="btn btn-link" rel="noopener noreferrer nofollow" href="{{ $place->source_url }}">Lihat sumber</a></p>
                @endif
                @if ($place->attribution)
                    <p class="typewriter">{{ $place->attribution }}</p>
                @endif
            </div>

            @if ($collections->isNotEmpty())
                <div class="mt-8 border-t border-brass/60 pt-4">
                    <h2 class="text-xl">Ada dalam koleksi</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach ($collections as $collection)
                            <li><a class="btn btn-link" href="{{ route('collections.show', $collection->slug) }}">{{ $collection->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </article>

    @if ($related->isNotEmpty())
        <section aria-labelledby="terkait" class="mt-20 border-t border-brass/60 pt-6">
            <h2 id="terkait" class="text-2xl">Tempat terkait</h2>
            <ul class="mt-8 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <li><x-dossier-card :place="$item" :saved="in_array($item->id, $saved, true)" /></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>

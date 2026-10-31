@props(['markers' => [], 'route' => null, 'label' => 'Peta lokasi', 'autoload' => true, 'height' => 'h-80 sm:h-[26rem]', 'swappable' => false])

@php
    $enabled = \App\Support\MapMarkers::enabled() && (count($markers) > 0 || $swappable);
    $first = $markers[0] ?? null;
    $payload = [
        'key' => config('archive.maps.browser_key'),
        'mapId' => config('archive.maps.map_id'),
        'markers' => array_values($markers),
        'route' => $route,
        'autoload' => $autoload,
    ];
@endphp

{{-- Tanpa key atau tanpa marker, komponen tidak merender apa pun. Halaman tetap utuh lewat tautan dan daftar. --}}
@if ($enabled)
    <section aria-label="{{ $label }}" x-data="archiveMap(@js($payload))" @if ($swappable) x-on:archive-map-swap.window="swap($event.detail)" @endif {{ $attributes }}>
        <div class="relative border border-ink/25 bg-surface">
            <div x-ref="canvas" class="{{ $height }} w-full"></div>

            <div x-show="state !== 'ready'" class="absolute inset-0 z-10 grid place-content-center gap-3 bg-surface p-6 text-center" role="status" aria-live="polite">
                <noscript><p>Peta membutuhkan JavaScript. Gunakan tautan Google Maps di halaman ini.</p></noscript>

                <template x-if="state === 'idle'">
                    <div class="space-y-3">
                        <p class="text-ink-soft">Peta belum dimuat.</p>
                        <button type="button" class="btn btn-secondary" x-on:click="load()">Tampilkan peta</button>
                    </div>
                </template>

                <template x-if="state === 'loading'">
                    <p class="typewriter" aria-busy="true">Memuat peta</p>
                </template>

                <template x-if="state === 'error'">
                    <div class="space-y-3">
                        <p class="font-semibold">Peta tidak dapat dimuat.</p>
                        <p class="text-sm text-ink-soft">Daftar dan tautan di halaman ini tetap bisa dipakai.</p>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <button type="button" class="btn btn-secondary" x-on:click="load()">Coba lagi</button>
                            @if ($first && ! $swappable)
                                <a class="btn btn-link" rel="noopener noreferrer" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ $first['lat'] }},{{ $first['lng'] }}">Buka di Google Maps</a>
                            @endif
                        </div>
                    </div>
                </template>
            </div>
        </div>

        @if ($route || $swappable)
            <div x-show="routeState !== 'none'" x-cloak class="mt-4 text-sm">
                <p x-show="routeState === 'loading'" role="status" class="typewriter">Menghitung rute</p>
                <p x-show="routeState === 'error'" role="status" class="font-semibold" x-text="routeError"></p>

                <div x-show="routeState === 'ready'" class="space-y-3">
                    <p class="font-semibold">
                        Jarak rute <span x-text="routeTotal ? routeTotal.distance : ''"></span>,
                        estimasi waktu <span x-text="routeTotal ? routeTotal.duration : ''"></span>
                    </p>

                    <ol class="divide-y divide-ink/20 border-y border-ink/20">
                        <template x-for="(leg, index) in routeLegs" x-bind:key="index">
                            <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                                <span x-text="leg.from + ' ke ' + leg.to"></span>
                                <span class="typewriter" x-text="'Jarak rute ' + leg.distance + ', estimasi waktu ' + leg.duration"></span>
                            </li>
                        </template>
                    </ol>

                    <ul class="list-disc space-y-1 pl-5 font-semibold" x-show="routeWarnings.length > 0">
                        <template x-for="(warning, index) in routeWarnings" x-bind:key="index">
                            <li x-text="warning"></li>
                        </template>
                    </ul>

                    <p class="text-ink-soft">Estimasi waktu bukan jaminan. Lalu lintas, penutupan jalan, kondisi jalan, dan cuaca dapat mengubahnya.</p>
                </div>
            </div>
        @endif
    </section>
@endif

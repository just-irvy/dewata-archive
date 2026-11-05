@props(['variant' => 'paper'])

{{--
| Masthead. Varian "paper" untuk semua halaman. Varian "overlay" hanya di beranda saat foto
| sampul dipasang: masthead menumpang di atas foto dengan teks terang (desain Stitch).
--}}
@php
    $overlay = $variant === 'overlay';
    $links = [
        ['route' => 'home', 'match' => 'home', 'label' => __('ui.nav.home')],
        ['route' => 'places.index', 'match' => 'places.*', 'label' => __('ui.nav.explore')],
        ['route' => 'regions.index', 'match' => 'regions.*', 'label' => __('ui.nav.regions')],
        ['route' => 'hotels.index', 'match' => 'hotels.*', 'label' => __('ui.nav.hotels')],
        ['route' => 'about', 'match' => 'about', 'label' => 'Tentang'],
    ];
    $user = auth()->user();
    $savedCount = $user ? $user->savedPlaces()->count() : null;
    $rule = $overlay ? 'border-white/25' : 'border-ink/20';
    // Overlay mengikuti margin desain Stitch (3,5rem di desktop), bukan kolom 6xl di tengah.
    // Lebar sama di semua halaman, agar masthead tidak bergeser saat berpindah halaman.
    $container = 'mx-auto w-full max-w-[120rem] px-5 sm:px-8 lg:px-14';
@endphp

<header
    data-masthead
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    @class([
        'relative z-30',
        'border-b-[3px] border-double border-ink bg-paper' => ! $overlay,
        'text-surface' => $overlay,
    ])
>
    <div class="{{ $container }}">
        {{-- Baris metadata: tempat, tanggal, dan akun --}}
        <div @class(['flex items-center justify-between gap-4 border-b', $rule, 'py-2' => ! $overlay, 'py-1.5' => $overlay])>
            <p @class(['typewriter truncate', 'text-surface/85' => $overlay])>
                <span @class(['font-bold uppercase', 'text-surface' => $overlay])>Bali</span>
                <span aria-hidden="true"> · </span>
                <time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, j F Y') }}</time>
            </p>

            <div class="flex shrink-0 items-center gap-4 text-sm">
                @auth
                    @if ($user->isAdmin())
                        <a href="{{ route('admin.index') }}" @class(['btn-link btn', 'text-surface' => $overlay])>{{ __('ui.nav.admin') }}</a>
                    @endif
                    <a href="{{ route('account.index') }}" @class(['btn-link btn', 'text-surface' => $overlay])>{{ __('ui.nav.account') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" @class(['btn-link btn', 'text-surface' => $overlay])>{{ __('ui.nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @class(['btn-link btn', 'text-surface' => $overlay])>{{ __('ui.nav.login') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" @class(['btn-link btn', 'text-surface' => $overlay])>{{ __('ui.nav.register') }}</a>
                    @endif
                @endauth
            </div>
        </div>

        {{-- Nameplate --}}
        <div class="flex items-end justify-between gap-6 py-3 lg:py-[1.6vh]">
            <div class="min-w-0">
                <a href="{{ route('home') }}" @class([
                    'block font-display font-extrabold leading-none tracking-tight [view-transition-name:nama-situs]',
                    'text-[1.65rem] uppercase sm:text-5xl lg:text-[clamp(2.25rem,5.4vh,3.5rem)]' => ! $overlay,
                    'text-[1.65rem] uppercase drop-shadow-[0_2px_4px_rgba(0,0,0,0.55)] sm:text-5xl lg:text-[clamp(2.25rem,5.4vh,3.5rem)]' => $overlay,
                ])>The Dewata Archive</a>
                <p @class([
                    'font-display italic leading-snug',
                    'mt-2' => ! $overlay,
                    'mt-1.5 sm:mt-2' => $overlay,
                    'text-[0.85rem] text-ink-soft sm:text-base lg:text-[clamp(0.95rem,2vh,1.125rem)]' => ! $overlay,
                    'text-[0.85rem] text-surface/90 sm:text-base lg:text-[clamp(0.95rem,2vh,1.125rem)]' => $overlay,
                ])>{{ __('ui.tagline') }}</p>
            </div>

            <div class="hidden shrink-0 items-center gap-2 md:flex">
                <a href="{{ route('places.index') }}" @class([
                    'kicker inline-flex min-h-10 items-center gap-2 border px-3',
                    'border-ink/40 text-ink hover:border-rust hover:text-rust' => ! $overlay,
                    'border-white/30 bg-white/10 text-surface backdrop-blur-sm hover:bg-white/20' => $overlay,
                ])>
                    <svg aria-hidden="true" viewBox="0 0 16 16" class="size-3.5"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m10.5 10.5 3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="square"/></svg>
                    Cari arsip
                </a>
                @if ($savedCount !== null)
                    <a href="{{ route('account.saved-places') }}" @class([
                        'kicker inline-flex min-h-10 items-center gap-2 border px-3',
                        'border-ink/40 text-ink hover:border-rust hover:text-rust' => ! $overlay,
                        'border-white/30 bg-white/10 text-surface backdrop-blur-sm hover:bg-white/20' => $overlay,
                    ])>
                        <svg aria-hidden="true" viewBox="0 0 16 16" @class(['size-3.5', 'text-rust' => ! $overlay, 'text-[#ffb4a3]' => $overlay])><path d="M4 2h8v12l-4-3-4 3z" fill="currentColor"/></svg>
                        Disimpan: {{ $savedCount }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Navigasi desktop --}}
        <nav aria-label="{{ __('ui.nav.label') }}" @class(['hidden items-center justify-between border-t md:flex', $rule, 'border-b' => $overlay])>
            <ul class="flex items-center gap-6 lg:gap-8">
                @foreach ($links as $link)
                    @php($active = request()->routeIs($link['match']))
                    <li>
                        <a
                            href="{{ route($link['route']) }}"
                            @if ($active) aria-current="page" @endif
                            @class([
                                'flex items-center border-b-2',
                                'kicker min-h-11',
                                'border-rust text-ink' => $active && ! $overlay,
                                'border-transparent text-ink-soft hover:text-ink' => ! $active && ! $overlay,
                                'border-[#d97757] text-surface' => $active && $overlay,
                                'border-transparent text-surface/80 hover:text-surface' => ! $active && $overlay,
                            ])
                        >{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
            @if (config('archive.features.planner'))
                @if ($overlay)
                    <a href="{{ route('planner.index') }}" class="kicker my-1.5 inline-flex min-h-10 items-center gap-2 border border-white/35 bg-white/10 px-4 text-surface backdrop-blur-sm hover:bg-white/20">{{ __('ui.nav.planner') }} <span aria-hidden="true" class="text-[#ffb4a3]">→</span></a>
                @else
                    <a href="{{ route('planner.index') }}" class="kicker my-1.5 inline-flex min-h-10 items-center gap-2 bg-rust px-4 text-surface hover:bg-rust-deep">{{ __('ui.nav.planner') }} <span aria-hidden="true">→</span></a>
                @endif
            @endif
        </nav>

        {{-- Navigasi mobile: tombol Planner tetap terlihat tanpa membuka menu --}}
        <div @class(['flex items-center justify-between gap-3 border-t py-2 md:hidden', $rule])>
            @if (config('archive.features.planner'))
                <x-button :href="route('planner.index')">{{ __('ui.nav.planner') }}</x-button>
            @else
                <span></span>
            @endif
            <button
                type="button"
                @class(['btn btn-secondary', 'border-white/60 text-surface hover:bg-white/10' => $overlay])
                x-on:click="open = ! open"
                x-bind:aria-expanded="open.toString()"
                aria-controls="menu-mobile"
            >
                <span x-text="open ? @js(__('ui.close_menu')) : @js(__('ui.menu'))">{{ __('ui.menu') }}</span>
            </button>
        </div>

        <nav id="menu-mobile" x-cloak x-show="open" aria-label="{{ __('ui.nav.label') }}" @class([
            'border-t pb-3 md:hidden',
            'border-ink/20' => ! $overlay,
            '-mx-4 border-white/25 bg-ink/95 px-4 sm:-mx-6 sm:px-6' => $overlay,
        ])>
            <ul>
                @foreach ($links as $link)
                    <li @class(['border-b last:border-b-0', 'border-ink/10' => ! $overlay, 'border-white/15' => $overlay])>
                        <a
                            href="{{ route($link['route']) }}"
                            @if (request()->routeIs($link['match'])) aria-current="page" @endif
                            @class([
                                'flex min-h-12 items-center text-base font-semibold',
                                'text-rust' => request()->routeIs($link['match']) && ! $overlay,
                                'text-[#ffb4a3]' => request()->routeIs($link['match']) && $overlay,
                            ])
                        >{{ $link['label'] }}</a>
                    </li>
                @endforeach
                <li @class(['border-t', 'border-ink/10' => ! $overlay, 'border-white/15' => $overlay])>
                    <a href="{{ route('places.index') }}" class="flex min-h-12 items-center text-base font-semibold">Cari arsip</a>
                </li>
                @if ($savedCount !== null)
                    <li><a href="{{ route('account.saved-places') }}" class="flex min-h-12 items-center text-base font-semibold">Disimpan: {{ $savedCount }}</a></li>
                @endif
            </ul>
        </nav>
    </div>
</header>

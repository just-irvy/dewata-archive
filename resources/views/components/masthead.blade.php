@php
    $links = [
        ['route' => 'home', 'match' => 'home', 'label' => __('ui.nav.home')],
        ['route' => 'places.index', 'match' => 'places.*', 'label' => __('ui.nav.explore')],
        ['route' => 'regions.index', 'match' => 'regions.*', 'label' => __('ui.nav.regions')],
        ['route' => 'hotels.index', 'match' => 'hotels.*', 'label' => __('ui.nav.hotels')],
    ];
    $user = auth()->user();
@endphp

<header
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    class="border-b-[3px] border-double border-ink bg-paper"
>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        {{-- Baris tanggal dan akun --}}
        <div class="flex items-center justify-between gap-4 border-b border-ink/20 py-2">
            <time class="typewriter" datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, j F Y') }}</time>

            <div class="flex items-center gap-4 text-sm">
                @auth
                    @if ($user->isAdmin())
                        <a href="{{ route('admin.index') }}" class="btn-link btn">{{ __('ui.nav.admin') }}</a>
                    @endif
                    <a href="{{ route('account.index') }}" class="btn-link btn">{{ __('ui.nav.account') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-link btn">{{ __('ui.nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-link btn">{{ __('ui.nav.login') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn-link btn">{{ __('ui.nav.register') }}</a>
                    @endif
                @endauth
            </div>
        </div>

        {{-- Nameplate --}}
        <div class="flex items-end justify-between gap-6 py-4 md:py-6">
            <a href="{{ route('home') }}" class="font-display text-[1.75rem] font-extrabold leading-none tracking-tight sm:text-5xl md:text-6xl">
                The Dewata Archive
            </a>
            <p class="hidden max-w-[17rem] pb-1 text-right text-sm leading-snug text-ink-soft md:block">{{ __('ui.tagline') }}</p>
        </div>

        {{-- Navigasi desktop --}}
        <nav aria-label="{{ __('ui.nav.label') }}" class="hidden items-center justify-between border-t border-ink/20 md:flex">
            <ul class="flex items-center gap-8">
                @foreach ($links as $link)
                    <li>
                        <a
                            href="{{ route($link['route']) }}"
                            @if (request()->routeIs($link['match'])) aria-current="page" @endif
                            @class([
                                'flex min-h-12 items-center border-b-2 text-[0.9375rem] font-semibold',
                                'border-rust text-ink' => request()->routeIs($link['match']),
                                'border-transparent text-ink-soft hover:text-ink' => ! request()->routeIs($link['match']),
                            ])
                        >{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
            <x-button :href="route('planner.index')" class="my-2">{{ __('ui.nav.planner') }}</x-button>
        </nav>

        {{-- Navigasi mobile: tombol Planner tetap terlihat tanpa membuka menu --}}
        <div class="flex items-center justify-between gap-3 border-t border-ink/20 py-2 md:hidden">
            <x-button :href="route('planner.index')">{{ __('ui.nav.planner') }}</x-button>
            <button
                type="button"
                class="btn btn-secondary"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open.toString()"
                aria-controls="menu-mobile"
            >
                <span x-text="open ? @js(__('ui.close_menu')) : @js(__('ui.menu'))">{{ __('ui.menu') }}</span>
            </button>
        </div>

        <nav id="menu-mobile" x-cloak x-show="open" aria-label="{{ __('ui.nav.label') }}" class="border-t border-ink/20 pb-3 md:hidden">
            <ul>
                @foreach ($links as $link)
                    <li class="border-b border-ink/10 last:border-b-0">
                        <a
                            href="{{ route($link['route']) }}"
                            @if (request()->routeIs($link['match'])) aria-current="page" @endif
                            @class([
                                'flex min-h-12 items-center text-base font-semibold',
                                'text-rust' => request()->routeIs($link['match']),
                            ])
                        >{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>

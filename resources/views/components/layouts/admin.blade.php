@props(['title' => null])
@php
    $nav = [
        ['admin.index', 'admin.index', 'Ringkasan'],
        ['admin.home.edit', 'admin.home.*', 'Beranda'],
        ['admin.places.index', 'admin.places.*', 'Tempat'],
        ['admin.hotels.index', 'admin.hotels.*', 'Hotel'],
        ['admin.regions.index', 'admin.regions.*', 'Wilayah'],
        ['admin.categories.index', 'admin.categories.*', 'Kategori'],
        ['admin.tags.index', 'admin.tags.*', 'Tag'],
        ['admin.collections.index', 'admin.collections.*', 'Koleksi'],
        ['admin.media.index', 'admin.media.*', 'Media'],
        ['admin.imports.index', 'admin.imports.*', 'Impor'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' | ' : '' }}Admin, The Dewata Archive</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    <a href="#konten-utama" class="btn btn-primary sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50">{{ __('ui.skip') }}</a>

    <header class="border-b-[3px] border-double border-ink">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 py-3">
                <p class="font-display text-xl font-extrabold">The Dewata Archive <span class="typewriter font-normal">Ruang kerja admin</span></p>
                <div class="flex items-center gap-4 text-sm">
                    <a class="btn btn-link" href="{{ route('home') }}">Lihat situs</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-link">{{ __('ui.nav.logout') }}</button></form>
                </div>
            </div>

            <nav aria-label="Navigasi admin" class="-mx-1 flex flex-wrap border-t border-ink/20">
                @foreach ($nav as [$route, $match, $label])
                    <a href="{{ route($route) }}" @if (request()->routeIs($match)) aria-current="page" @endif
                        @class([
                            'flex min-h-11 items-center border-b-2 px-3 text-[0.9375rem] font-semibold',
                            'border-rust text-ink' => request()->routeIs($match),
                            'border-transparent text-ink-soft hover:text-ink' => ! request()->routeIs($match),
                        ])>{{ $label }}</a>
                @endforeach
                @can('view-audit-logs')
                    <a href="{{ route('admin.audit-logs.index') }}" @if (request()->routeIs('admin.audit-logs.*')) aria-current="page" @endif
                        @class([
                            'flex min-h-11 items-center border-b-2 px-3 text-[0.9375rem] font-semibold',
                            'border-rust text-ink' => request()->routeIs('admin.audit-logs.*'),
                            'border-transparent text-ink-soft hover:text-ink' => ! request()->routeIs('admin.audit-logs.*'),
                        ])>Audit log</a>
                @endcan
            </nav>
        </div>
    </header>

    <main id="konten-utama" tabindex="-1" class="mx-auto w-full max-w-6xl px-4 py-8 outline-none sm:px-6">
        <x-flash />

        @if ($errors->has('publish'))
            <div role="alert" class="mb-8 border-l-4 border-oxblood bg-surface px-4 py-3">
                <p class="font-bold text-oxblood">Belum bisa diterbitkan</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->get('publish') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($errors->has('delete'))
            <div role="alert" class="mb-8 border-l-4 border-oxblood bg-surface px-4 py-3 text-sm font-semibold text-oxblood">
                {{ $errors->first('delete') }}
            </div>
        @endif

        @if ($errors->any() && ! $errors->has('publish') && ! $errors->has('delete'))
            <div role="alert" class="mb-8 border-l-4 border-oxblood bg-surface px-4 py-3">
                <p class="font-bold text-oxblood">Ada isian yang perlu diperbaiki</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>

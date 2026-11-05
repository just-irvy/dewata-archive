@props(['title' => null, 'description' => null, 'bleed' => false, 'masthead' => 'paper'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' | ' : '' }}The Dewata Archive</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh flex-col">
    <a href="#konten-utama" class="btn btn-primary sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50">
        {{ __('ui.skip') }}
    </a>

    {{-- masthead="none": halaman menaruh masthead sendiri, misalnya di atas foto sampul beranda. --}}
    @if ($masthead !== 'none')
        <x-masthead :variant="$masthead" />
    @endif

    @if ($bleed)
        {{-- Halaman selebar layar: halaman mengatur sendiri lebar dan jarak isinya. --}}
        <main id="konten-utama" tabindex="-1" class="w-full flex-1 outline-none">
            @if (session('status'))
                <div class="mx-auto w-full max-w-[120rem] px-5 pt-6 sm:px-8 lg:px-14"><x-flash /></div>
            @endif
            {{ $slot }}
        </main>
    @else
        <main id="konten-utama" tabindex="-1" class="mx-auto w-full max-w-[120rem] flex-1 px-5 py-10 outline-none sm:px-8 md:py-14 lg:px-14">
            <x-flash />
            {{ $slot }}
        </main>
    @endif

    <x-site-footer :wide="true" />

    <x-chat-widget />
</body>
</html>

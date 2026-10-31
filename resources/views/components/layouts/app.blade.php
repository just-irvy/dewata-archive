@props(['title' => null, 'description' => null])
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

    <x-masthead />

    <main id="konten-utama" tabindex="-1" class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 outline-none sm:px-6 md:py-14">
        <x-flash />
        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>

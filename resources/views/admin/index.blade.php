<x-layouts.app :title="__('ui.admin.title')">
    <h1 class="text-4xl">{{ __('ui.admin.title') }}</h1>
    <p class="mt-3 typewriter">{{ __('ui.admin.role') }}: {{ auth()->user()->role->value }}</p>
    <p class="mt-6 max-w-[62ch] text-ink-soft">Pengelolaan tempat, hotel, wilayah, koleksi, dan media dibangun di Fase 1.</p>
</x-layouts.app>

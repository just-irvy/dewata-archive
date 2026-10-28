<x-layouts.app :title="__('ui.account.title')">
    <h1 class="text-4xl">{{ __('ui.account.title') }}</h1>

    <dl class="mt-8 grid max-w-xl grid-cols-[auto_1fr] gap-x-8 gap-y-2">
        <dt class="font-semibold">{{ __('ui.auth.name') }}</dt>
        <dd>{{ auth()->user()->name }}</dd>
        <dt class="font-semibold">{{ __('ui.auth.email') }}</dt>
        <dd class="break-all">{{ auth()->user()->email }}</dd>
    </dl>

    <section class="mt-12 max-w-xl border-t border-brass/60 pt-6">
        <h2 class="text-2xl">Tempat tersimpan</h2>
        <p class="mt-3"><a class="btn btn-link" href="{{ route('account.saved-places') }}">Buka daftar tempat tersimpan</a></p>
    </section>

    <section class="mt-12 max-w-xl border-t border-brass/60 pt-6">
        <h2 class="text-2xl">Rencana tersimpan</h2>
        <p class="mt-3 text-ink-soft">{{ __('ui.account.empty_plans') }}</p>
        @if (config('archive.features.planner'))
            <p class="mt-4"><x-button :href="route('planner.index')" variant="secondary">{{ __('ui.nav.planner') }}</x-button></p>
        @endif
    </section>
</x-layouts.app>

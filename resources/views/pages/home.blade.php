<x-layouts.app :description="__('ui.home.description')">
    <section class="max-w-3xl">
        <h1 class="text-4xl sm:text-5xl md:text-6xl">{{ __('ui.home.title') }}</h1>
        <p class="mt-6 max-w-[62ch] text-lg leading-relaxed text-ink-soft">{{ __('ui.home.lead') }}</p>

        <div class="mt-8 flex flex-wrap items-center gap-4">
            <x-button :href="route('places.index')">{{ __('ui.home.cta_explore') }}</x-button>
            <a href="{{ route('planner.index') }}" class="btn btn-link">{{ __('ui.home.cta_planner') }}</a>
        </div>
    </section>

    @env('local')
        <p class="typewriter mt-16 max-w-xl border-t border-brass/60 pt-4">
            Penampung Fase 0. Dashboard editorial dibangun di Fase 2 setelah data tempat tersedia.
        </p>
    @endenv
</x-layouts.app>

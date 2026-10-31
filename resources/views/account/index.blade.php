<x-layouts.app :title="__('ui.account.title')">
    <h1 class="text-4xl">{{ __('ui.account.title') }}</h1>

    <dl class="mt-8 grid max-w-xl grid-cols-[auto_1fr] gap-x-8 gap-y-2">
        <dt class="font-semibold">{{ __('ui.auth.name') }}</dt>
        <dd>{{ auth()->user()->name }}</dd>
        <dt class="font-semibold">{{ __('ui.auth.email') }}</dt>
        <dd class="break-all">{{ auth()->user()->email }}</dd>
    </dl>

    <div class="mt-12 grid gap-12 lg:grid-cols-12">
        <section aria-labelledby="arsip" class="border-t border-brass/60 pt-6 lg:col-span-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="arsip" class="text-2xl">Arsip perjalanan</h2>
                @if ($planCount > 0)
                    <a class="btn btn-link" href="{{ route('account.plans.index') }}">Semua {{ $planCount }} arsip</a>
                @endif
            </div>

            @if ($plans->isEmpty())
                <p class="mt-3 text-ink-soft">{{ __('ui.account.empty_plans') }}</p>
            @else
                <ul class="mt-4 divide-y divide-ink/15 border-y border-ink/15">
                    @foreach ($plans as $plan)
                        <li class="py-3">
                            <a class="font-semibold hover:text-rust" href="{{ route('account.plans.show', $plan->id) }}">{{ $plan->title }}</a>
                            <p class="typewriter">{{ $plan->status->label() }}, {{ $plan->days_count }} hari, {{ $plan->items_count }} tempat</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (config('archive.features.planner'))
                <p class="mt-5"><x-button :href="route('planner.index')" variant="secondary">{{ __('ui.nav.planner') }}</x-button></p>
            @endif
        </section>

        <section aria-labelledby="tersimpan" class="border-t border-brass/60 pt-6 lg:col-span-5">
            <h2 id="tersimpan" class="text-2xl">Tempat tersimpan</h2>
            <p class="mt-3 text-ink-soft">{{ $savedCount > 0 ? $savedCount.' tempat ditandai.' : 'Belum ada tempat yang ditandai.' }}</p>
            <p class="mt-3"><a class="btn btn-link" href="{{ route('account.saved-places') }}">Buka daftar tempat tersimpan</a></p>
        </section>
    </div>
</x-layouts.app>

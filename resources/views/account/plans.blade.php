<x-layouts.app title="Arsip saya">
    <nav aria-label="Jejak halaman"><a class="typewriter underline underline-offset-4" href="{{ route('account.index') }}">Akun</a></nav>
    <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">Arsip saya</h1>
        <x-button :href="route('planner.index')" variant="secondary">Mulai arsip baru</x-button>
    </div>

    @if ($plans->isEmpty())
        <div class="mt-10 max-w-lg border-t border-brass/60 pt-6">
            <h2 class="text-2xl">Belum ada arsip perjalanan</h2>
            <p class="mt-2 text-ink-soft">Susun dari rekomendasi atau pilih sendiri setiap tempat. Arsip yang Anda buat saat masuk otomatis tersimpan di sini.</p>
            <p class="mt-4"><x-button :href="route('planner.index')">Rencanakan perjalanan</x-button></p>
        </div>
    @else
        <ul class="mt-10 divide-y divide-ink/15 border-y-2 border-ink">
            @foreach ($plans as $plan)
                @php($regions = $plan->days->pluck('region.name')->filter()->unique()->values())
                <li class="grid gap-x-6 gap-y-2 py-5 sm:grid-cols-[1fr_auto] sm:items-start">
                    <div>
                        <p class="typewriter">{{ $plan->status->label() }}, {{ $plan->creation_mode === \App\Enums\CreationMode::Recommended ? 'rekomendasi' : 'manual' }}</p>
                        <h2 class="mt-1 font-display text-2xl font-bold"><a class="hover:text-rust" href="{{ route('account.plans.show', $plan->id) }}">{{ $plan->title }}</a></h2>
                        <p class="mt-1 text-[0.9375rem] text-ink-soft">
                            {{ $plan->days_count }} hari, {{ $plan->items_count }} tempat{{ $regions->isNotEmpty() ? ', '.\App\Support\Words::join($regions->all()) : '' }}{{ $plan->start_date ? ', mulai '.$plan->start_date->translatedFormat('j F Y') : '' }}
                        </p>
                    </div>
                    <p class="typewriter sm:text-right">Diubah {{ $plan->updated_at->translatedFormat('j F Y') }}</p>
                </li>
            @endforeach
        </ul>
        <div class="mt-10">{{ $plans->links() }}</div>
    @endif
</x-layouts.app>

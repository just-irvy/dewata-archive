@props(['lastUpdated' => null])

<footer class="mt-auto border-t-[3px] border-double border-ink bg-paper">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-12">
        <div class="md:col-span-5">
            <p class="font-display text-xl font-bold">The Dewata Archive</p>
            <p class="mt-2 max-w-sm text-sm text-ink-soft">{{ __('ui.footer.disclaimer') }}</p>
        </div>

        <nav aria-label="Tautan footer" class="md:col-span-3 md:col-start-7">
            <ul class="space-y-1 text-sm">
                <li><a class="btn-link btn" href="{{ route('about') }}">{{ __('ui.footer.about') }}</a></li>
                <li><a class="btn-link btn" href="{{ route('about') }}#sumber-data">{{ __('ui.footer.sources') }}</a></li>
                <li><a class="btn-link btn" href="{{ route('about') }}#privasi">{{ __('ui.footer.privacy') }}</a></li>
            </ul>
        </nav>

        <p class="typewriter md:col-span-3">
            {{ __('ui.footer.updated') }}:
            @if ($lastUpdated)
                <time datetime="{{ $lastUpdated->toIso8601String() }}">{{ $lastUpdated->translatedFormat('j F Y') }}</time>
            @else
                {{ __('ui.footer.no_content') }}
            @endif
        </p>
    </div>
</footer>

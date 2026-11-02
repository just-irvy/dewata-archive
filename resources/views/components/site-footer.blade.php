@props(['lastUpdated' => null, 'wide' => false])

{{-- Footer berkolom (desain Stitch). Hanya menautkan halaman yang benar-benar ada. --}}
<footer class="mt-auto border-t-[3px] border-double border-ink bg-surface">
    <div @class(['mx-auto py-10', 'max-w-6xl px-4 sm:px-6' => ! $wide, 'w-full max-w-[120rem] px-5 sm:px-8 lg:px-14' => $wide])>
        <div class="grid gap-8 border-b border-ink/20 pb-8 md:grid-cols-12">
            <div class="md:col-span-6">
                <p class="font-display text-2xl font-bold uppercase tracking-tight">The Dewata Archive</p>
                <p class="mt-3 max-w-md text-sm text-ink-soft">{{ __('ui.footer.disclaimer') }}</p>
                <p class="typewriter mt-3"><span class="font-bold text-ink">Tanpa iklan.</span> Tidak menerima pemesanan atau komisi.</p>
            </div>

            <nav aria-label="Direktori arsip" class="md:col-span-3">
                <p class="kicker text-ink">Direktori</p>
                <ul class="mt-3 space-y-1.5 text-sm">
                    <li><a class="text-ink-soft hover:text-rust" href="{{ route('about') }}">{{ __('ui.footer.about') }}</a></li>
                    <li><a class="text-ink-soft hover:text-rust" href="{{ route('about') }}#sumber-data">{{ __('ui.footer.sources') }}</a></li>
                    <li><a class="text-ink-soft hover:text-rust" href="{{ route('about') }}#perencana">Cara perencana bekerja</a></li>
                    <li><a class="text-ink-soft hover:text-rust" href="{{ route('regions.index') }}">Indeks wilayah</a></li>
                </ul>
            </nav>

            <nav aria-label="Kebijakan dan akun" class="md:col-span-3">
                <p class="kicker text-ink">Kebijakan dan akun</p>
                <ul class="mt-3 space-y-1.5 text-sm">
                    <li><a class="text-ink-soft hover:text-rust" href="{{ route('about') }}#privasi">{{ __('ui.footer.privacy') }}</a></li>
                    @auth
                        <li><a class="text-ink-soft hover:text-rust" href="{{ route('account.plans.index') }}">Arsip perjalanan saya</a></li>
                        <li><a class="text-ink-soft hover:text-rust" href="{{ route('account.saved-places') }}">Tempat tersimpan</a></li>
                    @else
                        <li><a class="text-ink-soft hover:text-rust" href="{{ route('login') }}">Masuk ke akun</a></li>
                    @endauth
                </ul>
            </nav>
        </div>

        <p class="typewriter pt-5">
            {{ __('ui.footer.updated') }}:
            @if ($lastUpdated)
                <time datetime="{{ $lastUpdated->toIso8601String() }}">{{ $lastUpdated->translatedFormat('j F Y') }}</time>
            @else
                {{ __('ui.footer.no_content') }}
            @endif
        </p>
    </div>
</footer>

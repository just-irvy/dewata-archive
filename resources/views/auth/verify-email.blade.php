<x-layouts.app :title="__('ui.auth.verify_title')">
    <div class="mx-auto max-w-md">
        <h1 class="text-4xl">{{ __('ui.auth.verify_title') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('ui.auth.verify_intro') }}</p>

        @if (session('status') === 'verification-link-sent')
            <div role="status" class="mt-6 border-l-4 border-moss bg-surface px-4 py-3 text-sm font-semibold">
                {{ __('ui.auth.verify_resent') }}
            </div>
        @endif

        <div class="mt-8 flex flex-wrap items-center gap-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button type="submit">{{ __('ui.auth.resend') }}</x-button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link">{{ __('ui.nav.logout') }}</button>
            </form>
        </div>
    </div>
</x-layouts.app>

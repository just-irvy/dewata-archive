<x-layouts.app :title="__('ui.auth.login_title')">
    <div class="mx-auto max-w-md">
        <h1 class="text-4xl">{{ __('ui.auth.login_title') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('ui.auth.login_intro') }}</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            <x-field name="email" type="email" :label="__('ui.auth.email')" required autocomplete="username" autofocus />
            <x-field name="password" type="password" :label="__('ui.auth.password')" required autocomplete="current-password" />

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input type="checkbox" name="remember" value="1" class="size-5 accent-rust">
                <span>{{ __('ui.auth.remember') }}</span>
            </label>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <x-button type="submit">{{ __('ui.auth.submit_login') }}</x-button>
                <a class="btn btn-link" href="{{ route('password.request') }}">{{ __('ui.auth.forgot_link') }}</a>
            </div>
        </form>

        @if (Route::has('register'))
            <p class="mt-8 border-t border-brass/60 pt-4 text-sm">
                <a class="btn btn-link" href="{{ route('register') }}">{{ __('ui.auth.to_register') }}</a>
            </p>
        @endif
    </div>
</x-layouts.app>

<x-layouts.app :title="__('ui.auth.register_title')">
    <div class="mx-auto max-w-md">
        <h1 class="text-4xl">{{ __('ui.auth.register_title') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('ui.auth.register_intro') }}</p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
            @csrf
            <x-field name="name" :label="__('ui.auth.name')" required autocomplete="name" autofocus />
            <x-field name="email" type="email" :label="__('ui.auth.email')" required autocomplete="username" />
            <x-field name="password" type="password" :label="__('ui.auth.password')" :hint="__('ui.auth.password_hint')" required autocomplete="new-password" />
            <x-field name="password_confirmation" type="password" :label="__('ui.auth.password_confirm')" required autocomplete="new-password" />

            <x-button type="submit">{{ __('ui.auth.submit_register') }}</x-button>
        </form>

        <p class="mt-8 border-t border-brass/60 pt-4 text-sm">
            <a class="btn btn-link" href="{{ route('login') }}">{{ __('ui.auth.to_login') }}</a>
        </p>
    </div>
</x-layouts.app>

<x-layouts.app :title="__('ui.auth.forgot_title')">
    <div class="mx-auto max-w-md">
        <h1 class="text-4xl">{{ __('ui.auth.forgot_title') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('ui.auth.forgot_intro') }}</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            <x-field name="email" type="email" :label="__('ui.auth.email')" required autocomplete="username" autofocus />
            <x-button type="submit">{{ __('ui.auth.submit_forgot') }}</x-button>
        </form>

        <p class="mt-8 border-t border-brass/60 pt-4 text-sm">
            <a class="btn btn-link" href="{{ route('login') }}">{{ __('ui.auth.back_login') }}</a>
        </p>
    </div>
</x-layouts.app>

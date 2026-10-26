<x-layouts.app :title="__('ui.auth.reset_title')">
    <div class="mx-auto max-w-md">
        <h1 class="text-4xl">{{ __('ui.auth.reset_title') }}</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-field name="email" type="email" :label="__('ui.auth.email')" :value="$request->email" required autocomplete="username" />
            <x-field name="password" type="password" :label="__('ui.auth.password_new')" :hint="__('ui.auth.password_hint')" required autocomplete="new-password" autofocus />
            <x-field name="password_confirmation" type="password" :label="__('ui.auth.password_confirm')" required autocomplete="new-password" />
            <x-button type="submit">{{ __('ui.auth.submit_reset') }}</x-button>
        </form>
    </div>
</x-layouts.app>

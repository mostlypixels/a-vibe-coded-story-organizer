<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-field name="email" :label="__('Email')">
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', app()->environment('local') ? 'admin@example.com' : '')" required autofocus autocomplete="username" />
        </x-field>

        <x-field name="password" :label="__('Password')" class="mt-4">

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            :value="app()->environment('local') ? 'password' : ''"
                            required autocomplete="current-password" />
        </x-field>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded-sm border-border-strong text-link shadow-xs focus:ring-focus" name="remember">
                <span class="ms-2 text-sm text-content-muted">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-content-muted hover:text-content rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-focus" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-button variant="primary" class="ms-3">
                {{ __('Log in') }}
            </x-button>
        </div>
    </form>
</x-guest-layout>

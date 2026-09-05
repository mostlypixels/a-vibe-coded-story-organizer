<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-field name="name" :label="__('Name')">
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
        </x-field>

        <x-field name="email" :label="__('Email')" class="mt-4">
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
        </x-field>

        <x-field name="password" :label="__('Password')" class="mt-4">

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
        </x-field>

        <x-field name="password_confirmation" :label="__('Confirm Password')" class="mt-4">

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />
        </x-field>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-content-muted hover:text-content rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-focus" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-button variant="primary" class="ms-4">
                {{ __('Register') }}
            </x-button>
        </div>
    </form>
</x-guest-layout>

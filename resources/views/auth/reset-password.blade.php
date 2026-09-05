<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field name="email" :label="__('Email')">
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        </x-field>

        <x-field name="password" :label="__('Password')" class="mt-4">
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
        </x-field>

        <x-field name="password_confirmation" :label="__('Confirm Password')" class="mt-4">

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
        </x-field>

        <div class="flex items-center justify-end mt-4">
            <x-button variant="primary">
                {{ __('Reset Password') }}
            </x-button>
        </div>
    </form>
</x-guest-layout>

<x-guest-layout>
    <div class="mb-4 text-sm text-content-muted">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <x-field name="password" :label="__('Password')">

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
        </x-field>

        <div class="flex justify-end mt-4">
            <x-button variant="primary">
                {{ __('Confirm') }}
            </x-button>
        </div>
    </form>
</x-guest-layout>

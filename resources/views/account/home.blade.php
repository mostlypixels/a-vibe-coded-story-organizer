<x-app-layout>
    <x-page-heading>{{ __('Account') }}</x-page-heading>

    <div class="grid gap-6 md:grid-cols-2">
        <x-card :title="__('Profile')" icon="tabler-user" stretch flush-footer>
            <p>{{ __('Your name, email and password.') }}</p>

            <x-slot:footer>
                <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-link hover:underline">
                    {{ __('View profile') }}
                </a>
            </x-slot:footer>
        </x-card>

        <x-card :title="__('Configuration')" icon="tabler-settings" stretch flush-footer>
            <p>{{ __('App-wide settings: general options, appearance and data.') }}</p>

            <x-slot:footer>
                <a href="{{ route('admin.index') }}" class="text-sm font-medium text-link hover:underline">
                    {{ __('View configuration') }}
                </a>
            </x-slot:footer>
        </x-card>
    </div>
</x-app-layout>

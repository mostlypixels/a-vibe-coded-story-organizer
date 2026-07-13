<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Profile') }}
        </x-heading>
    </x-slot>

    <div class="space-y-6">
        @include('profile.partials.update-profile-information-form')

        @include('profile.partials.update-password-form')

        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>

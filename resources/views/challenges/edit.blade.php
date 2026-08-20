<x-app-layout>
    <x-page-heading>
        {{ __('Edit Challenge') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="challenge-edit-form" method="POST" action="{{ route('challenges.update', $challenge) }}">
                @csrf
                @method('PUT')

                @include('challenges.partials.fields')
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions form="challenge-edit-form">
                {{ __('Delete Challenge') }}
            </x-edit-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

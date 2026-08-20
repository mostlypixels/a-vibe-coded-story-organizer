<x-app-layout>
    <x-page-heading>
        {{ __('New Challenge') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="challenge-create-form" method="POST" action="{{ route('projects.challenges.store', $project) }}">
                @csrf

                @include('challenges.partials.fields')
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="challenge-create-form" :cancel="route('projects.progress', $project)">
                {{ __('Create Challenge') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

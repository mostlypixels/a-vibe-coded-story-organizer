<x-app-layout>
    <x-page-heading>
        {{ __('New Attribute') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="codex-attribute-create-form" method="POST" action="{{ route('projects.codex-attributes.store', $project) }}" class="space-y-6">
                @csrf

                @include('codex-attributes.partials.fields')
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="codex-attribute-create-form" :cancel="route('projects.codex-attributes.index', $project)">
                {{ __('Create Attribute') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

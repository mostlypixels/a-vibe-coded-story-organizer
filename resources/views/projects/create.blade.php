<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New Project') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="project-create-form" method="POST" action="{{ route('projects.store') }}" class="space-y-6">
                @csrf

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                </x-field>

                <x-field name="description" :label="__('Description')">
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                </x-field>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="project-create-form" :cancel="route('projects.index')">
                {{ __('Create Project') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

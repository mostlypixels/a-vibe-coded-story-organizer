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

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="project-create-form" :cancel="route('projects.index')">
                {{ __('Create Project') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

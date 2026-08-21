<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Welcome') }}
        </x-heading>
    </x-slot>

    <x-card class="text-center">
        <div class="mx-auto max-w-md space-y-4 py-6">
            <x-heading level="3">{{ __('Create your first project') }}</x-heading>

            <p class="text-content-muted">
                {{ __('A project holds your books, scenes, codex and timeline. Start one to begin writing.') }}
            </p>

            <x-button variant="primary" :href="route('projects.create')">
                {{ __('New Project') }}
            </x-button>
        </div>
    </x-card>
</x-app-layout>

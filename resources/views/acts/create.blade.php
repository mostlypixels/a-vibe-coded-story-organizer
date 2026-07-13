<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New Act') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('projects.acts.store', $project) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Title')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. The Curse of Pressine') }}" required autofocus />
                    <p class="mt-1 text-sm text-gray-500">{{ __('The act number is assigned automatically and can be changed later by reordering.') }}</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-button variant="primary">{{ __('Create Act') }}</x-button>
                </div>
            </form>
        </x-card>
    </x-edit-layout>
</x-app-layout>

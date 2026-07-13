<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New Plotline') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('projects.plotlines.store', $project) }}" class="space-y-6">
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

                <div>
                    <x-input-label :value="__('Color')" />
                    <x-color-picker name="color" :selected="old('color')" />
                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-button variant="primary">{{ __('Create Plotline') }}</x-button>
                </div>
            </form>
        </x-card>
    </x-edit-layout>
</x-app-layout>

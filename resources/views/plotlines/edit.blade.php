<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Plotline') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('plotlines.update', $plotline) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $plotline->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-autosave-field entity="plotline" :model="$plotline" field="description" :label="__('Description')" />
                </div>

                <div>
                    <x-input-label :value="__('Color')" />
                    <x-color-picker name="color" :selected="old('color', $plotline->color)" />
                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>
                </div>
            </form>

            <x-delete-button :action="route('plotlines.destroy', $plotline)" :confirm="__('Are you sure you want to delete this plotline?')" class="mt-6">
                {{ __('Delete Plotline') }}
            </x-delete-button>
        </x-card>
    </x-edit-layout>
</x-app-layout>

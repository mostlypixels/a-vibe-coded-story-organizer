<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Plotline') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
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
                        <x-input-label for="description" :value="__('Description')" />
                        <x-wysiwyg id="description" name="description" :value="old('description', $plotline->description)" :rows="4" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :value="__('Color')" />
                        <x-color-picker name="color" :selected="old('color', $plotline->color)" />
                        <x-input-error :messages="$errors->get('color')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('plotlines.destroy', $plotline) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this plotline?') }}')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Delete Plotline') }}</x-danger-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>

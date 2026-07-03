<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Act') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form method="POST" action="{{ route('acts.update', $act) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" :value="__('Title')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $act->name)" placeholder="{{ __('e.g. The Curse of Pressine') }}" required autofocus />
                        <p class="mt-1 text-sm text-gray-500">{{ __('Currently act #:position. Use the move up/down buttons on the list to reorder.', ['position' => $act->position]) }}</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('description', $act->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('acts.destroy', $act) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this act?') }}')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Delete Act') }}</x-danger-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>

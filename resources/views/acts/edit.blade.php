<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Act') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="act-edit-form" method="POST" action="{{ route('acts.update', $act) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="name" :value="__('Title')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $act->name)" placeholder="{{ __('e.g. The Curse of Pressine') }}" required autofocus />
                    <p class="mt-1 text-sm text-gray-500">{{ __('Currently act #:position. Use the move up/down buttons on the list to reorder.', ['position' => $act->position]) }}</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-autosave-field entity="act" :model="$act" field="description" :label="__('Description')" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions
                form="act-edit-form"
                :delete-action="route('acts.destroy', $act)"
                :delete-confirm="__('Are you sure you want to delete this act?')"
            >
                {{ __('Delete Act') }}
            </x-edit-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

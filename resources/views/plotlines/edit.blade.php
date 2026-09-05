<x-app-layout>
    <x-page-heading>
        {{ __('Edit Plotline') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="plotline-edit-form" method="POST" action="{{ route('plotlines.update', $plotline) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $plotline->name)" required autofocus />
                </x-field>

                <div>
                    <x-autosave-field entity="plotline" :model="$plotline" field="description" :label="__('Description')" />
                </div>

                <div>
                    <x-input-label :value="__('Color')" />
                    <x-color-picker name="color" :selected="old('color', $plotline->color)" />
                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions
                form="plotline-edit-form"
                :history-model="$plotline"
                :delete-action="$plotline->is_main ? null : route('plotlines.destroy', $plotline)"
                :delete-confirm="__('Are you sure you want to delete this plotline?')"
            >
                {{ __('Delete Plotline') }}
            </x-edit-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

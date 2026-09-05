<x-app-layout>
    <x-page-heading>
        {{ __('New Plotline') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="plotline-create-form" method="POST" action="{{ route('projects.plotlines.store', $project) }}" class="space-y-6">
                @csrf

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                </x-field>

                <x-field name="description" :label="__('Description')">
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                </x-field>

                <div>
                    <x-input-label :value="__('Color')" />
                    <x-color-picker name="color" :selected="old('color')" />
                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="plotline-create-form" :cancel="route('projects.plotlines.index', $project)">
                {{ __('Create Plotline') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

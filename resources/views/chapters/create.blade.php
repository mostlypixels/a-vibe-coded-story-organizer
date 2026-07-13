<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New Chapter') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('projects.chapters.store', $project) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="act_id" :value="__('Act')" />
                    <select id="act_id" name="act_id" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                        <option value="">{{ __('Select an act...') }}</option>
                        @foreach ($project->acts as $act)
                            <option value="{{ $act->id }}" @selected(old('act_id') == $act->id)>{{ $act->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('act_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" :value="__('Title')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. The Oath at the Fountain') }}" required autofocus />
                    <p class="mt-1 text-sm text-gray-500">{{ __('The chapter number is assigned automatically and can be changed later by reordering.') }}</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-button variant="primary">{{ __('Create Chapter') }}</x-button>
                </div>
            </form>
        </x-card>
    </x-edit-layout>
</x-app-layout>

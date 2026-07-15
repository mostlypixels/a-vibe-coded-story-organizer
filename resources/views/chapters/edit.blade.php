<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Chapter') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="chapter-edit-form" method="POST" action="{{ route('chapters.update', $chapter) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="act_id" :value="__('Act')" />
                    <select id="act_id" name="act_id" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                        @foreach ($project->acts as $act)
                            <option value="{{ $act->id }}" @selected(old('act_id', $chapter->act_id) == $act->id)>{{ $act->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('act_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" :value="__('Title')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $chapter->name)" placeholder="{{ __('e.g. The Oath at the Fountain') }}" required autofocus />
                    <p class="mt-1 text-sm text-gray-500">{{ __('Currently chapter #:position within its act. Use the move up/down buttons on the list to reorder.', ['position' => $chapter->position]) }}</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description', $chapter->description)" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

            </form>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions
                form="chapter-edit-form"
                :delete-action="route('chapters.destroy', $chapter)"
                :delete-confirm="__('Are you sure you want to delete this chapter?')"
            >
                {{ __('Delete Chapter') }}
            </x-edit-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

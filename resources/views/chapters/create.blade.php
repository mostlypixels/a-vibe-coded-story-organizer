<x-app-layout>
    <x-page-heading>
        {{ __('New Chapter') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="chapter-create-form" method="POST" action="{{ route('projects.chapters.store', $project) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="act_id" :value="__('Act')" />
                    <x-select id="act_id" name="act_id" class="mt-1 block w-full" required>
                        <option value="">{{ __('Select an act...') }}</option>
                        @foreach ($project->acts as $act)
                            <option value="{{ $act->id }}" @selected(old('act_id') == $act->id)>{{ $act->name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('act_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" :value="__('Title')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. The Oath at the Fountain') }}" required autofocus />
                    <p class="mt-1 text-sm text-content-muted">{{ __('The chapter number is assigned automatically and can be changed later by reordering.') }}</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="chapter-create-form" :cancel="route('projects.chapters.index', $project)">
                {{ __('Create Chapter') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

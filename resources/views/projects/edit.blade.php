@php
    use App\Support\CodexMediaRules;

    // The cover is a plain path column on the public disk; resolve its public URL for
    // the preview (no codex_media row, so no ->url() helper as on CodexMedia).
    $coverUrl = $project->cover_image
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($project->cover_image)
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Project') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="project-edit-form" method="POST" action="{{ route('projects.update', $project) }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $project->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description', $project->description)" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>
                </div>
            </form>

            <x-delete-button :action="route('projects.destroy', $project)" :confirm="__('Are you sure you want to delete this project?')" class="mt-6">
                {{ __('Delete Project') }}
            </x-delete-button>
        </x-card>

        <x-slot:sidebar>
            <x-card :title="$coverUrl ? __('Replace cover image') : __('Cover image')">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $project->name }}" class="w-full rounded-md border border-gray-200 object-cover">

                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remove_cover_image" value="1" form="project-edit-form" class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                        {{ __('Remove cover image') }}
                    </label>
                @endif

                <input id="cover_image" name="cover_image" type="file" form="project-edit-form" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-400">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
            </x-card>

            <x-card :title="__('Book metadata')">
                <p class="text-sm text-gray-500">{{ __('Used when exporting this project as an EPUB.') }}</p>

                <div class="mt-4 space-y-6">
                    <div>
                        <x-input-label for="language" :value="__('Language')" />
                        <select id="language" name="language" form="project-edit-form" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                            @foreach (\App\Enums\BookLanguage::cases() as $language)
                                <option value="{{ $language->value }}" @selected(old('language', $project->language->value) === $language->value)>{{ $language->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('language')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="author" :value="__('Author')" />
                        <x-text-input id="author" name="author" form="project-edit-form" type="text" class="mt-1 block w-full" :value="old('author', $project->author)" />
                        <x-input-error :messages="$errors->get('author')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="publisher" :value="__('Publisher')" />
                        <x-text-input id="publisher" name="publisher" form="project-edit-form" type="text" class="mt-1 block w-full" :value="old('publisher', $project->publisher)" />
                        <x-input-error :messages="$errors->get('publisher')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="rights" :value="__('Rights')" />
                        <textarea id="rights" name="rights" form="project-edit-form" rows="3" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('rights', $project->rights) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">{{ __('Copyright or rights statement.') }}</p>
                        <x-input-error :messages="$errors->get('rights')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="isbn" :value="__('ISBN')" />
                        <x-text-input id="isbn" name="isbn" form="project-edit-form" type="text" class="mt-1 block w-full" :value="old('isbn', $project->isbn)" />
                        <p class="mt-1 text-xs text-gray-400">{{ __('ISBN-13, with or without hyphens.') }}</p>
                        <x-input-error :messages="$errors->get('isbn')" class="mt-2" />
                    </div>
                </div>
            </x-card>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

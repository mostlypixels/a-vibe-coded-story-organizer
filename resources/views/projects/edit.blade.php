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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Project') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-6" enctype="multipart/form-data">
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

                    {{-- Book metadata: optional fields that feed the epub export's OPF. --}}
                    <fieldset class="border-t border-gray-200 pt-6 space-y-6">
                        <legend class="text-sm font-medium text-gray-700">{{ __('Book metadata') }}</legend>
                        <p class="text-sm text-gray-500">{{ __('Used when exporting this project as an EPUB.') }}</p>

                        <div>
                            <x-input-label for="language" :value="__('Language')" />
                            <select id="language" name="language" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                                @foreach (\App\Enums\BookLanguage::cases() as $language)
                                    <option value="{{ $language->value }}" @selected(old('language', $project->language->value) === $language->value)>{{ $language->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('language')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="author" :value="__('Author')" />
                            <x-text-input id="author" name="author" type="text" class="mt-1 block w-full" :value="old('author', $project->author)" />
                            <x-input-error :messages="$errors->get('author')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="publisher" :value="__('Publisher')" />
                            <x-text-input id="publisher" name="publisher" type="text" class="mt-1 block w-full" :value="old('publisher', $project->publisher)" />
                            <x-input-error :messages="$errors->get('publisher')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="rights" :value="__('Rights')" />
                            <textarea id="rights" name="rights" rows="3" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('rights', $project->rights) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">{{ __('Copyright or rights statement.') }}</p>
                            <x-input-error :messages="$errors->get('rights')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="isbn" :value="__('ISBN')" />
                            <x-text-input id="isbn" name="isbn" type="text" class="mt-1 block w-full" :value="old('isbn', $project->isbn)" />
                            <p class="mt-1 text-xs text-gray-400">{{ __('ISBN-13, with or without hyphens.') }}</p>
                            <x-input-error :messages="$errors->get('isbn')" class="mt-2" />
                        </div>

                        {{-- Cover: single image, mirrors the Codex cover-upload pattern
                             (preview + remove checkbox + file input), but stored as a plain
                             path column rather than a codex_media row. --}}
                        <div>
                            <x-input-label for="cover_image" :value="$coverUrl ? __('Replace cover image') : __('Cover image')" />

                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ $project->name }}" class="mt-2 w-40 rounded-md border border-gray-200 object-cover">

                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="remove_cover_image" value="1" class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                                    {{ __('Remove cover image') }}
                                </label>
                            @endif

                            <input id="cover_image" name="cover_image" type="file" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                            <p class="mt-1 text-xs text-gray-400">{{ CodexMediaRules::imageHint() }}</p>
                            <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
                        </div>
                    </fieldset>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('projects.destroy', $project) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this project?') }}')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Delete Project') }}</x-danger-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>

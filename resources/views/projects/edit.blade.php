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
        @if (session('status') === 'codex-references-synced')
            <div class="mb-6 rounded-md bg-success-surface p-4 text-sm text-success-surface-content">
                {{ __('Codex references resynced for every scene in this project.') }}
            </div>
        @endif

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
                    <x-autosave-field entity="project" :model="$project" field="description" :label="__('Description')" />
                </div>

            </form>
        </x-card>

        <x-card :title="__('Book metadata')">
            <p class="text-sm text-content-muted">{{ __('Used when exporting this project as an EPUB.') }}</p>

            <div class="mt-4 space-y-6">
                <div>
                    <x-input-label for="language" :value="__('Language')" />
                    <x-select id="language" name="language" form="project-edit-form" class="mt-1 block w-full" required>
                        @foreach (\App\Enums\BookLanguage::cases() as $language)
                            <option value="{{ $language->value }}" @selected(old('language', $project->language->value) === $language->value)>{{ $language->label() }}</option>
                        @endforeach
                    </x-select>
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
                    <x-autosave-field entity="project" :model="$project" field="rights" :label="__('Rights')" :rows="3" form="project-edit-form" />
                    <p class="mt-1 text-xs text-content-subtle">{{ __('Copyright or rights statement.') }}</p>
                </div>

                <div>
                    <x-input-label for="isbn" :value="__('ISBN')" />
                    <x-text-input id="isbn" name="isbn" form="project-edit-form" type="text" class="mt-1 block w-full" :value="old('isbn', $project->isbn)" />
                    <p class="mt-1 text-xs text-content-subtle">{{ __('ISBN-13, with or without hyphens.') }}</p>
                    <x-input-error :messages="$errors->get('isbn')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('Book front & back matter (Markdown)')">
            <p class="text-sm text-content-muted">{{ __('Optional pages included in the EPUB export when enabled on the Export-ebook configuration page. These fields use Markdown (like scene contents), not the rich-text editor above.') }}</p>

            <div class="mt-4 space-y-6">
                <div>
                    <x-autosave-field entity="project" :model="$project" field="dedication" :label="__('Dedication')" form="project-edit-form" />
                </div>

                <div>
                    <x-autosave-field entity="project" :model="$project" field="acknowledgements" :label="__('Acknowledgements')" form="project-edit-form" />
                </div>

                <div>
                    <x-autosave-field entity="project" :model="$project" field="preface" :label="__('Preface')" form="project-edit-form" />
                </div>

                <div>
                    <x-autosave-field entity="project" :model="$project" field="postface" :label="__('Postface')" form="project-edit-form" />
                    <p class="mt-1 text-xs text-content-subtle">{{ __('Rendered before any codex appendix.') }}</p>
                </div>
            </div>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions
                form="project-edit-form"
                :history-model="$project"
                :delete-action="route('projects.destroy', $project)"
                :delete-confirm="$deleteConfirm"
            >
                {{ __('Delete Project') }}
            </x-edit-actions>

            <x-card :title="$coverUrl ? __('Replace cover image') : __('Cover image')">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $project->name }}" class="w-full rounded-md border border-border object-cover">

                    <label class="mt-2 flex items-center gap-2 text-sm text-content-muted">
                        <input type="checkbox" name="remove_cover_image" value="1" form="project-edit-form" class="rounded-sm border-border-strong text-link focus:ring-focus">
                        {{ __('Remove cover image') }}
                    </label>
                @endif

                <input id="cover_image" name="cover_image" type="file" form="project-edit-form" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-2 block w-full text-sm text-content-muted file:mr-3 file:rounded-md file:border-0 file:bg-neutral file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-content hover:file:bg-neutral/80">
                <p class="mt-1 text-xs text-content-subtle">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
            </x-card>
        </x-slot:sidebar>
    </x-edit-layout>

    <x-card :title="__('Codex references')" class="mt-6">
        <p class="text-sm text-content-muted">
            {{ __('Rebuild which codex entries every scene in this project references, from scratch. Scenes and codex entries keep this in sync automatically as you edit them — use this only to backfill existing scenes or recover from a suspected mismatch.') }}
        </p>
        <form method="POST" action="{{ route('projects.codex-references.sync', $project) }}" class="mt-3">
            @csrf
            <x-button variant="secondary">{{ __('Resync codex references') }}</x-button>
        </form>
    </x-card>
</x-app-layout>

@php
    use App\Support\CodexMediaRules;

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

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $project->name)" required autofocus />
                </x-field>

                <div>
                    <x-autosave-field entity="project" :model="$project" field="description" :label="__('Description')" />
                </div>

            </form>
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

            <x-card :title="__('Writing goals')">
                <p class="text-sm text-content-muted">{{ __('Shown on the Progress page and the project dashboard. Leave a field empty for no goal.') }}</p>

                <div class="mt-4 space-y-6">
                    <x-field name="daily_word_goal" :label="__('Daily word goal')">
                        <x-text-input id="daily_word_goal" name="daily_word_goal" form="project-edit-form" type="number" min="0" placeholder="{{ __('Leave empty for no goal') }}" class="mt-1 block w-full" :value="old('daily_word_goal', $project->daily_word_goal)" />
                    </x-field>

                    <x-field name="total_word_goal" :label="__('Total word goal')">
                        <x-text-input id="total_word_goal" name="total_word_goal" form="project-edit-form" type="number" min="0" placeholder="{{ __('Leave empty for no goal') }}" class="mt-1 block w-full" :value="old('total_word_goal', $project->total_word_goal)" />
                    </x-field>
                </div>
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

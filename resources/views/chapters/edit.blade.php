@php
    use App\Support\CodexMediaRules;

    // The cover is a plain path column on the public disk; resolve its public URL for
    // the preview (no codex_media row, so no ->url() helper as on CodexMedia).
    $coverUrl = $chapter->cover_image
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($chapter->cover_image)
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Chapter') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="chapter-edit-form" method="POST" action="{{ route('chapters.update', $chapter) }}" class="space-y-6" enctype="multipart/form-data">
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
                    <x-autosave-field entity="chapter" :model="$chapter" field="description" :label="__('Description')" />
                </div>

            </form>
        </x-card>

        <x-slot:sidebar>
            @if ($chapter->scenes_count > 0)
                {{-- Chapter has scenes: offer "move them elsewhere, then delete" or a full cascade. --}}
                <x-edit-actions form="chapter-edit-form">
                    <x-slot:delete>
                        <x-button
                            variant="danger"
                            type="button"
                            :icon="true"
                            class="w-full"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 'delete-chapter-{{ $chapter->id }}')"
                        >
                            {{ __('Delete Chapter') }}
                        </x-button>
                    </x-slot:delete>
                </x-edit-actions>

                <x-delete-with-move-dialog
                    name="delete-chapter-{{ $chapter->id }}"
                    :action="route('chapters.destroy', $chapter)"
                    :title="__('Delete Chapter?')"
                    :child-count="$chapter->scenes_count"
                    child-singular="scene"
                    child-plural="scenes"
                    destination-noun="chapter"
                    :destinations="$destinations"
                />
            @else
                {{-- No scenes: nothing to move or count — keep the original plain confirm(). --}}
                <x-edit-actions
                    form="chapter-edit-form"
                    :delete-action="route('chapters.destroy', $chapter)"
                    :delete-confirm="__('Are you sure you want to delete this chapter?')"
                >
                    {{ __('Delete Chapter') }}
                </x-edit-actions>
            @endif

            <x-card :title="$coverUrl ? __('Replace cover image') : __('Cover image')">
                <p class="text-sm text-gray-500">{{ __('Optional. Included before this chapter in the EPUB export when chapter covers are enabled.') }}</p>

                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $chapter->name }}" class="mt-3 w-full rounded-md border border-gray-200 object-cover">

                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remove_cover_image" value="1" form="chapter-edit-form" class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                        {{ __('Remove cover image') }}
                    </label>
                @endif

                <input id="cover_image" name="cover_image" type="file" form="chapter-edit-form" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-400">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
            </x-card>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

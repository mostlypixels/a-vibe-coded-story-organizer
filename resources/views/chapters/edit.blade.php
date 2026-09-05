@php
    use App\Support\CodexMediaRules;

    $coverUrl = $chapter->cover_image
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($chapter->cover_image)
        : null;
@endphp

<x-app-layout>
    <x-page-heading>
        {{ __('Edit Chapter') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="chapter-edit-form" method="POST" action="{{ route('chapters.update', $chapter) }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <x-field name="act_id" :label="__('Act')">
                    <x-select id="act_id" name="act_id" class="mt-1 block w-full" required>
                        @foreach ($acts as $act)
                            <option value="{{ $act->id }}" @selected(old('act_id', $chapter->act_id) == $act->id)>{{ $act->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="name" :label="__('Title')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $chapter->name)" placeholder="{{ __('e.g. The Oath at the Fountain') }}" required autofocus />
                    <p class="mt-1 text-sm text-content-muted">{{ __('Chapter :number — :position of :total in :act. Use the move up/down buttons on the list to reorder.', [
                        'number' => $numbering->chapter($chapter),
                        'position' => $positionInAct,
                        'total' => $totalInAct,
                        'act' => __('Act :number', ['number' => $numbering->act($chapter->act)]),
                    ]) }}</p>
                </x-field>

                <div>
                    <x-autosave-field entity="chapter" :model="$chapter" field="description" :label="__('Description')" />
                </div>

            </form>
        </x-card>

        <x-slot:sidebar>
            @if ($chapter->scenes_count > 0)
                <x-edit-actions form="chapter-edit-form" :history-model="$chapter">
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
                <x-edit-actions
                    form="chapter-edit-form"
                    :history-model="$chapter"
                    :delete-action="route('chapters.destroy', $chapter)"
                    :delete-confirm="__('Are you sure you want to delete this chapter?')"
                >
                    {{ __('Delete Chapter') }}
                </x-edit-actions>
            @endif

            <x-card :title="$coverUrl ? __('Replace cover image') : __('Cover image')">
                <p class="text-sm text-content-muted">{{ __('Optional. Included before this chapter in the EPUB export when chapter covers are enabled.') }}</p>

                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $chapter->name }}" class="mt-3 w-full rounded-md border border-border object-cover">

                    <label class="mt-2 flex items-center gap-2 text-sm text-content-muted">
                        <input type="checkbox" name="remove_cover_image" value="1" form="chapter-edit-form" class="rounded-sm border-border-strong text-link focus:ring-focus">
                        {{ __('Remove cover image') }}
                    </label>
                @endif

                <input id="cover_image" name="cover_image" type="file" form="chapter-edit-form" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-2 block w-full text-sm text-content-muted file:mr-3 file:rounded-md file:border-0 file:bg-neutral file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-content hover:file:bg-neutral/80">
                <p class="mt-1 text-xs text-content-subtle">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
            </x-card>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

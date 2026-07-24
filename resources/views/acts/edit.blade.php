<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Act') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="act-edit-form" method="POST" action="{{ route('acts.update', $act) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="name" :value="__('Title')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $act->name)" placeholder="{{ __('e.g. The Curse of Pressine') }}" required autofocus />
                    <p class="mt-1 text-sm text-gray-500">{{ __('Currently act #:position. Use the move up/down buttons on the list to reorder.', ['position' => $act->position]) }}</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-autosave-field entity="act" :model="$act" field="description" :label="__('Description')" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            @if ($act->chapters_count > 0)
                {{-- Act has chapters: offer "move them elsewhere, then delete" or a full cascade. --}}
                <x-edit-actions form="act-edit-form">
                    <x-slot:delete>
                        <x-button
                            variant="danger"
                            type="button"
                            :icon="true"
                            class="w-full"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 'delete-act-{{ $act->id }}')"
                        >
                            {{ __('Delete Act') }}
                        </x-button>
                    </x-slot:delete>
                </x-edit-actions>

                <x-delete-with-move-dialog
                    name="delete-act-{{ $act->id }}"
                    :action="route('acts.destroy', $act)"
                    :title="__('Delete Act?')"
                    :child-count="$act->chapters_count"
                    child-singular="chapter"
                    child-plural="chapters"
                    destination-noun="act"
                    :secondary-count="$sceneCount"
                    secondary-singular="scene"
                    secondary-plural="scenes"
                    :destinations="$destinations"
                />
            @else
                {{-- No chapters: nothing to move or count — keep the original plain confirm(). --}}
                <x-edit-actions
                    form="act-edit-form"
                    :delete-action="route('acts.destroy', $act)"
                    :delete-confirm="__('Are you sure you want to delete this act?')"
                >
                    {{ __('Delete Act') }}
                </x-edit-actions>
            @endif
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

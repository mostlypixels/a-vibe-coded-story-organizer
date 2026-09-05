<x-app-layout>
    <x-page-heading>
        {{ __('Edit Act') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="act-edit-form" method="POST" action="{{ route('acts.update', $act) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-field name="name" :label="__('Title')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $act->name)" placeholder="{{ __('e.g. The Curse of Pressine') }}" required autofocus />
                    <p class="mt-1 text-sm text-content-muted">{{ __('Act :number of :total. Use the move up/down buttons on the list to reorder.', [
                        'number' => $numbering->act($act),
                        'total' => $totalActs,
                    ]) }}</p>
                </x-field>

                <div>
                    <x-autosave-field entity="act" :model="$act" field="description" :label="__('Description')" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            @if ($act->chapters_count > 0)
                <x-edit-actions form="act-edit-form" :history-model="$act">
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
                <x-edit-actions
                    form="act-edit-form"
                    :history-model="$act"
                    :delete-action="route('acts.destroy', $act)"
                    :delete-confirm="__('Are you sure you want to delete this act?')"
                >
                    {{ __('Delete Act') }}
                </x-edit-actions>
            @endif

            @if ($destinationBooks->isNotEmpty())
                <x-card :title="__('Move to another book')">
                    <p class="text-sm text-content-muted">{{ __('Move this act, with its chapters and scenes, to another book in this project.') }}</p>

                    <form method="POST" action="{{ route('acts.move-to-book', $act) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="move-to-book-id" :value="__('Destination book')" class="sr-only" />
                            <x-select id="move-to-book-id" name="book_id" class="block w-full sm:text-sm" required>
                                @foreach ($destinationBooks as $destinationBook)
                                    <option value="{{ $destinationBook->id }}">{{ $destinationBook->displayName() }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('book_id')" class="mt-2" />
                        </div>

                        <x-button variant="secondary" type="submit" class="w-full">{{ __('Move act') }}</x-button>
                    </form>
                </x-card>
            @endif
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

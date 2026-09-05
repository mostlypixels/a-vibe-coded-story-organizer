<x-app-layout>
    <x-page-heading>
        {{ __('New Scene') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
                <form id="scene-create-form" method="POST" action="{{ route('books.scenes.store', $book) }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="chapter_id" :value="__('Chapter')" />
                        <x-select id="chapter_id" name="chapter_id" class="mt-1 block w-full" required>
                            <option value="">{{ __('Select a chapter...') }}</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected(old('chapter_id') == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('chapter_id')" class="mt-2" />
                    </div>

                    <x-single-event-field
                        name="event_id"
                        :label="__('Happens during')"
                        :events="$events"
                        :empty-label="__('— Not assigned —')"
                        :window-min="$windowMin"
                        :window-max="$windowMax"
                    />

                    <div>
                        <x-input-label for="name" :value="__('Title')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. A Lady at the Fountain') }}" required autofocus />
                        <p class="mt-1 text-sm text-content-muted">{{ __('The scene number is assigned automatically and can be changed later by reordering.') }}</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <x-select id="status" name="status" class="mt-1 block w-full" required>
                            @foreach (\App\Enums\SceneStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', 'draft') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="contents" :value="__('Contents (Markdown)')" />
                        <x-wysiwyg id="contents" name="contents" :value="old('contents')" :rows="12" markdown />
                        <x-input-error :messages="$errors->get('contents')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Notes')" />
                        <x-wysiwyg id="notes" name="notes" :value="old('notes')" :rows="6" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :value="__('Mentions events')" />
                        <p class="text-sm text-content-muted">{{ __('Other events this scene refers to (optional).') }}</p>
                        <x-event-picker name="mentioned_events" :events="$events" :selected="old('mentioned_events', [])" />
                        <x-input-error :messages="$errors->get('mentioned_events')" class="mt-2" />
                    </div>
                </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="scene-create-form" :cancel="route('books.scenes.index', $book)">
                {{ __('Create Scene') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

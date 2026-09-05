<x-app-layout>
    <x-page-heading>
        {{ __('New Scene') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
                <form id="scene-create-form" method="POST" action="{{ route('books.scenes.store', $book) }}" class="space-y-6">
                    @csrf

                    <x-field name="chapter_id" :label="__('Chapter')">
                        <x-select id="chapter_id" name="chapter_id" class="mt-1 block w-full" required>
                            <option value="">{{ __('Select a chapter...') }}</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected(old('chapter_id') == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-single-event-field
                        name="event_id"
                        :label="__('Happens during')"
                        :events="$events"
                        :empty-label="__('— Not assigned —')"
                        :window-min="$windowMin"
                        :window-max="$windowMax"
                    />

                    <x-field name="name" :label="__('Title')" :hint="__('The scene number is assigned automatically and can be changed later by reordering.')">
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. A Lady at the Fountain') }}" required autofocus />
                    </x-field>

                    <x-field name="status" :label="__('Status')">
                        <x-select id="status" name="status" class="mt-1 block w-full" required>
                            @foreach (\App\Enums\SceneStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', 'draft') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field name="description" :label="__('Description')">
                        <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    </x-field>

                    <x-field name="contents" :label="__('Contents (Markdown)')">
                        <x-wysiwyg id="contents" name="contents" :value="old('contents')" :rows="12" markdown />
                    </x-field>

                    <x-field name="notes" :label="__('Notes')">
                        <x-wysiwyg id="notes" name="notes" :value="old('notes')" :rows="6" />
                    </x-field>

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

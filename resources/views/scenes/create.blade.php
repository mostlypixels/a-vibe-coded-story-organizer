<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Scene') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                    <form method="POST" action="{{ route('projects.scenes.store', $project) }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="chapter_id" :value="__('Chapter')" />
                            <select id="chapter_id" name="chapter_id" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                                <option value="">{{ __('Select a chapter...') }}</option>
                                @foreach ($chapters as $chapter)
                                    <option value="{{ $chapter->id }}" @selected(old('chapter_id') == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('chapter_id')" class="mt-2" />
                        </div>

                        <div x-data="{ newEvent: {{ old('new_event_title') ? 'true' : 'false' }} }">
                            <x-input-label for="event_id" :value="__('Happens during')" />
                            <select id="event_id" name="event_id" x-bind:disabled="newEvent" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm disabled:bg-gray-100 disabled:text-gray-400">
                                <option value="">{{ __('— Not assigned —') }}</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->title }} &mdash; {{ $event->event_datetime->format('M j, Y') }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('event_id')" class="mt-2" />

                            <button type="button" @click="newEvent = ! newEvent" class="mt-2 text-sm text-ocean-600 hover:text-ocean-800">
                                <span x-show="! newEvent">{{ __('+ New event') }}</span>
                                <span x-show="newEvent">{{ __('Cancel new event') }}</span>
                            </button>

                            <div x-show="newEvent" style="{{ old('new_event_title') ? '' : 'display: none;' }}" class="mt-3 space-y-3 border-l-2 border-ocean-200 pl-4">
                                <div>
                                    <x-input-label for="new_event_title" :value="__('New event title')" />
                                    <x-text-input id="new_event_title" name="new_event_title" type="text" class="mt-1 block w-full" :value="old('new_event_title')" />
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Created and attached to the Main plotline.') }}</p>
                                    <x-input-error :messages="$errors->get('new_event_title')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="new_event_datetime" :value="__('New event date & time')" />
                                    <x-text-input id="new_event_datetime" name="new_event_datetime" type="datetime-local" class="mt-1 block w-full" :value="old('new_event_datetime')" />
                                    <x-input-error :messages="$errors->get('new_event_datetime')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Title')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. A Lady at the Fountain') }}" required autofocus />
                            <p class="mt-1 text-sm text-gray-500">{{ __('The scene number is assigned automatically and can be changed later by reordering.') }}</p>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                                @foreach (\App\Enums\SceneStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', 'draft') === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
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
                            <p class="text-sm text-gray-500">{{ __('Other events this scene refers to (optional).') }}</p>
                            <x-event-picker name="mentioned_events" :events="$events" :selected="old('mentioned_events', [])" />
                            <x-input-error :messages="$errors->get('mentioned_events')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Create Scene') }}</x-primary-button>
                        </div>
                    </form>
            </x-card>
        </div>
    </div>
</x-app-layout>

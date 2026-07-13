<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Scene') }}
        </x-heading>
    </x-slot>

    @php
        $shareDurations = config('sharing.scene_link_durations');
        $shareDefaultKey = config('sharing.scene_link_default_duration');
        $shareDefaultDuration = $shareDurations[$shareDefaultKey] ?? reset($shareDurations);
    @endphp

    <x-edit-layout>
        <x-card>
                <form method="POST" action="{{ route('scenes.update', $scene) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="chapter_id" :value="__('Chapter')" />
                        <select id="chapter_id" name="chapter_id" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected(old('chapter_id', $scene->chapter_id) == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('chapter_id')" class="mt-2" />
                    </div>

                    <div x-data="{ newEvent: {{ old('new_event_title') ? 'true' : 'false' }} }">
                        <x-input-label for="event_id" :value="__('Happens during')" />
                        <select id="event_id" name="event_id" x-bind:disabled="newEvent" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm disabled:bg-gray-100 disabled:text-gray-400">
                            <option value="">{{ __('— Not assigned —') }}</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}" @selected(old('event_id', $scene->event_id) == $event->id)>{{ $event->title }} &mdash; {{ $event->event_datetime->format('M j, Y') }}</option>
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
                                <x-text-input id="new_event_datetime" name="new_event_datetime" type="datetime-local" class="mt-1 block w-full" :value="old('new_event_datetime')" min="{{ $windowMin }}" max="{{ $windowMax }}" />
                                <x-input-error :messages="$errors->get('new_event_datetime')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('Title')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $scene->name)" placeholder="{{ __('e.g. A Lady at the Fountain') }}" required autofocus />
                        <p class="mt-1 text-sm text-gray-500">{{ __('Currently scene #:position within its chapter. Use the move up/down buttons on the list to reorder.', ['position' => $scene->position]) }}</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                            @foreach (\App\Enums\SceneStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $scene->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <x-wysiwyg id="description" name="description" :value="old('description', $scene->description)" :rows="4" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="contents" :value="__('Contents (Markdown)')" />
                        <x-wysiwyg id="contents" name="contents" :value="old('contents', $scene->contents)" :rows="12" markdown />
                        <x-input-error :messages="$errors->get('contents')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Notes')" />
                        <x-wysiwyg id="notes" name="notes" :value="old('notes', $scene->notes)" :rows="6" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :value="__('Mentions events')" />
                        <p class="text-sm text-gray-500">{{ __('Other events this scene refers to (optional).') }}</p>
                        <x-event-picker name="mentioned_events" :events="$events" :selected="old('mentioned_events', $scene->mentionedEvents->pluck('id')->all())" />
                        <x-input-error :messages="$errors->get('mentioned_events')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>
                    </div>
                </form>

                <x-delete-button :action="route('scenes.destroy', $scene)" :confirm="__('Are you sure you want to delete this scene?')" class="mt-6">
                    {{ __('Delete Scene') }}
                </x-delete-button>
        </x-card>

        <x-slot:sidebar>
            <x-card :title="__('Share this scene')">
                @if (! $scene->isShared())
                    <form method="POST" action="{{ route('scenes.share.store', $scene) }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="duration" :value="__('Link duration')" />
                            <select id="duration" name="duration" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">
                                @foreach ($shareDurations as $label => $value)
                                    <option value="{{ $value }}" @selected(old('duration', $shareDefaultDuration) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('Creates a public, read-only link. Choose how long it stays valid: :choices.', ['choices' => implode(', ', array_keys($shareDurations))]) }}
                            </p>
                            <x-input-error :messages="$errors->get('duration')" class="mt-2" />
                        </div>

                        <x-button variant="primary">{{ __('Generate share link') }}</x-button>
                    </form>
                @else
                    <div class="space-y-4" x-data="{ copied: false }">
                        <div>
                            <x-input-label for="share_url" :value="__('Public link')" />
                            <div class="mt-1 flex gap-2">
                                <x-text-input
                                    id="share_url"
                                    type="text"
                                    class="block w-full font-mono text-sm"
                                    :value="$scene->shareUrl()"
                                    readonly
                                    x-ref="shareUrl"
                                    @focus="$el.select()"
                                />
                                <x-button
                                    variant="secondary"
                                    type="button"
                                    aria-label="{{ __('Copy share link to clipboard') }}"
                                    x-on:click="navigator.clipboard.writeText($refs.shareUrl.value); copied = true; setTimeout(() => copied = false, 2000)"
                                >
                                    <span x-show="! copied">{{ __('Copy') }}</span>
                                    <span x-show="copied" style="display: none;">{{ __('Copied!') }}</span>
                                </x-button>
                            </div>
                        </div>

                        <p class="text-sm text-gray-500">
                            {{ __('Expires :relative (:absolute).', [
                                'relative' => $scene->share_expires_at->diffForHumans(),
                                'absolute' => $scene->share_expires_at->format('M j, Y H:i'),
                            ]) }}
                        </p>

                        <div class="flex items-center gap-3">
                            <form method="POST" action="{{ route('scenes.share.store', $scene) }}">
                                @csrf
                                <input type="hidden" name="duration" value="{{ $shareDefaultDuration }}">
                                <x-button variant="secondary" type="button">{{ __('Regenerate') }}</x-button>
                            </form>

                            <x-delete-button :action="route('scenes.share.destroy', $scene)" :confirm="__('Revoke this share link? The current URL will stop working.')">
                                {{ __('Revoke') }}
                            </x-delete-button>
                        </div>
                    </div>
                @endif
            </x-card>

            @include('codex.partials.as-of', [
                'title' => __('Codex as of this scene'),
                'moment' => $scene->event,
                'groups' => $codexAsOfGroups,
            ])
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>

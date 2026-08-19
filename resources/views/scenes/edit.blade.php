<x-app-layout>
    <x-page-heading>
        {{ __('Edit Scene') }}
    </x-page-heading>

    @php
        $shareDurations = config('sharing.scene_link_durations');
        $shareDefaultKey = config('sharing.scene_link_default_duration');
        $shareDefaultDuration = $shareDurations[$shareDefaultKey] ?? reset($shareDurations);
    @endphp

    <x-edit-layout>
        <x-card>
                <form id="scene-edit-form" method="POST" action="{{ route('scenes.update', $scene) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="chapter_id" :value="__('Chapter')" />
                        <x-select id="chapter_id" name="chapter_id" class="mt-1 block w-full" required>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected(old('chapter_id', $scene->chapter_id) == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('chapter_id')" class="mt-2" />
                    </div>

                    <div x-data="{ newEvent: {{ old('new_event_title') ? 'true' : 'false' }} }">
                        <x-input-label for="event_id" :value="__('Happens during')" />
                        <x-select id="event_id" name="event_id" x-bind:disabled="newEvent" class="mt-1 block w-full disabled:bg-surface-sunken disabled:text-content-subtle">
                            <option value="">{{ __('— Not assigned —') }}</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}" @selected(old('event_id', $scene->event_id) == $event->id)>{{ $event->title }} &mdash; {{ $event->event_datetime->format('M j, Y') }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('event_id')" class="mt-2" />

                        <button type="button" @click="newEvent = ! newEvent" class="mt-2 text-sm text-link hover:text-link-hover">
                            <span x-show="! newEvent">{{ __('+ New event') }}</span>
                            <span x-show="newEvent">{{ __('Cancel new event') }}</span>
                        </button>

                        <div x-show="newEvent" style="{{ old('new_event_title') ? '' : 'display: none;' }}" class="mt-3 space-y-3 border-l-2 border-border pl-4">
                            <div>
                                <x-input-label for="new_event_title" :value="__('New event title')" />
                                <x-text-input id="new_event_title" name="new_event_title" type="text" class="mt-1 block w-full" :value="old('new_event_title')" />
                                <p class="mt-1 text-sm text-content-muted">{{ __('Created and attached to the Main plotline.') }}</p>
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
                        <p class="mt-1 text-sm text-content-muted">{{ __('Scene :number — :position of :total in :chapter. Use the move up/down buttons on the list to reorder.', [
                            'number' => $numbering->scene($scene),
                            'position' => $positionInChapter,
                            'total' => $totalInChapter,
                            'chapter' => __('Chapter :number', ['number' => $numbering->chapter($scene->chapter)]),
                        ]) }}</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <x-select id="status" name="status" class="mt-1 block w-full" required>
                            @foreach (\App\Enums\SceneStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $scene->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-autosave-field entity="scene" :model="$scene" field="description" :label="__('Description')" />
                    </div>

                    <div>
                        <x-autosave-field entity="scene" :model="$scene" field="contents" :label="__('Contents (Markdown)')" :rows="12" />
                    </div>

                    <div>
                        <x-autosave-field entity="scene" :model="$scene" field="notes" :label="__('Notes')" :rows="6" />
                    </div>

                    <div>
                        <x-input-label :value="__('Mentions events')" />
                        <p class="text-sm text-content-muted">{{ __('Other events this scene refers to (optional).') }}</p>
                        <x-event-picker name="mentioned_events" :events="$events" :selected="old('mentioned_events', $scene->mentionedEvents->pluck('id')->all())" />
                        <x-input-error :messages="$errors->get('mentioned_events')" class="mt-2" />
                    </div>

                </form>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions
                form="scene-edit-form"
                :history-model="$scene"
                :delete-action="route('scenes.destroy', $scene)"
                :delete-confirm="__('Are you sure you want to delete this scene?')"
                duplicate-modal="duplicate-scene-{{ $scene->id }}"
            >
                {{ __('Delete Scene') }}
            </x-edit-actions>

            <x-duplicate-dialog
                name="duplicate-scene-{{ $scene->id }}"
                :action="route('scenes.duplicate', $scene)"
                :title="__('Duplicate Scene?')"
                :suggestion="$duplicateSuggestion"
            />

            <x-collapsible-card :title="__('Share this scene')">
                @if (! $scene->isShared())
                    <form method="POST" action="{{ route('scenes.share.store', $scene) }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="duration" :value="__('Link duration')" />
                            <x-select id="duration" name="duration" class="mt-1 block w-full">
                                @foreach ($shareDurations as $label => $value)
                                    <option value="{{ $value }}" @selected(old('duration', $shareDefaultDuration) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-select>
                            <p class="mt-1 text-sm text-content-muted">
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
                                <button
                                    type="button"
                                    class="inline-flex w-9 shrink-0 items-center justify-center rounded-md border border-link bg-transparent text-link hover:bg-info-surface"
                                    :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy share link to clipboard') }}'"
                                    x-on:click="navigator.clipboard.writeText($refs.shareUrl.value); copied = true; setTimeout(() => copied = false, 2000)"
                                >
                                    <span class="sr-only" x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy share link to clipboard') }}'"></span>
                                    <x-tabler-check class="h-4 w-4" x-show="copied" style="display: none;" />
                                    <x-tabler-copy class="h-4 w-4" x-show="! copied" />
                                </button>
                            </div>
                        </div>

                        <p class="text-sm text-content-muted">
                            {{ __('Expires :relative (:absolute).', [
                                'relative' => $scene->share_expires_at->diffForHumans(),
                                'absolute' => $scene->share_expires_at->format('M j, Y H:i'),
                            ]) }}
                        </p>

                        <div class="flex gap-3">
                            <form method="POST" action="{{ route('scenes.share.store', $scene) }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="duration" value="{{ $shareDefaultDuration }}">
                                <x-button variant="secondary" type="button" icon="tabler-refresh" class="w-full">{{ __('Regenerate') }}</x-button>
                            </form>

                            <x-icon-delete-button
                                :action="route('scenes.share.destroy', $scene)"
                                :confirm="__('Revoke this share link? The current URL will stop working.')"
                                :label="__('Revoke')"
                                class="w-9"
                            />
                        </div>
                    </div>
                @endif
            </x-collapsible-card>
        </x-slot:sidebar>
    </x-edit-layout>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-9">
            @include('codex.partials.as-of', [
                'title' => __('Codex as of this scene'),
                'moment' => $scene->event,
                'groups' => $codexAsOfGroups,
            ])
        </div>

        <div class="lg:col-span-3">
            <x-collapsible-card :title="__('Codex references')">
                <p class="text-sm text-content-muted">{{ __('Detected from the scene contents on last save.') }}</p>

                @if ($referencedEntries->isEmpty())
                    <p class="mt-2 text-sm text-content-muted">{{ __('No codex entries referenced yet.') }}</p>
                @else
                    <ul class="mt-2 space-y-1">
                        @foreach ($referencedEntries as $entry)
                            <li>
                                <a href="{{ route('codex.edit', $entry) }}" class="text-sm text-link hover:text-link-hover">
                                    {{ $entry->name }}
                                </a>
                                <span class="text-xs text-content-subtle">({{ $entry->type->label() }})</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-collapsible-card>
        </div>
    </div>
</x-app-layout>

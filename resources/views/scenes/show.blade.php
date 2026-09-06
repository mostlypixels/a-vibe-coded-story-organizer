<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1" class="flex items-center gap-2">
                {{ __('Scene :number', ['number' => $numbering->scene($scene)]) }} &mdash; {{ $scene->name }}
                <x-scene-status-badge :status="$scene->status" />
            </x-heading>
            <p class="text-sm text-content-muted">
                {{ $scene->chapter->act->name }}
                &middot;
                {{ $scene->chapter->name }}
                &middot;
                <x-word-count :count="$scene->word_count" variant="inline" />
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('scenes.edit', $scene)" />
            <x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-scene-'.$scene->id" :label="__('Duplicate')" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'scene', 'id' => $scene->id]) }}" />
            <x-icon-delete-button :action="route('scenes.destroy', $scene)" :confirm="__('Are you sure you want to delete this scene?')" />
        </div>
    </div>

    <div class="space-y-6">
        @if (filled($scene->description))
            <x-card :title="__('Description')">
                <x-rich-text :html="$scene->description" />
            </x-card>
        @endif

        @if (filled($scene->contents))
            <x-card :title="__('Prose')">
                <x-scene-prose :scene="$scene" />
            </x-card>
        @endif

        @if (filled($scene->notes))
            <x-card :title="__('Notes')">
                <x-rich-text :html="$scene->notes" />
            </x-card>
        @endif

        @if ($scene->event)
            <x-card :title="__('Happens during')">
                <a href="{{ route('events.show', $scene->event) }}" class="text-link hover:text-link-hover">{{ $scene->event->title }}</a>
                <span class="text-sm text-content-muted"> &middot; <x-date :value="$scene->event->event_datetime" with-time /></span>
            </x-card>
        @endif

        @if ($scene->mentionedEvents->isNotEmpty())
            <x-card :title="__('Mentions')">
                <x-table>
                    <x-slot:head>
                        <x-table-heading>{{ __('Event') }}</x-table-heading>
                        <x-table-heading>{{ __('Date') }}</x-table-heading>
                    </x-slot:head>

                    @foreach ($scene->mentionedEvents as $event)
                        <x-table-row :striped="$loop->even">
                            <x-table-cell>
                                <a href="{{ route('events.show', $event) }}" class="text-link hover:text-link-hover">{{ $event->title }}</a>
                            </x-table-cell>
                            <x-table-cell muted><x-date :value="$event->event_datetime" /></x-table-cell>
                        </x-table-row>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        @if ($referencedEntries->isNotEmpty())
            <x-card :title="__('Codex references')">
                <ul class="space-y-1">
                    @foreach ($referencedEntries as $entry)
                        <li>
                            <a href="{{ route('codex.show', $entry) }}" class="text-sm text-link hover:text-link-hover">{{ $entry->name }}</a>
                            <span class="text-xs text-content-subtle">({{ $entry->type->label() }})</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>

    <x-duplicate-dialog
        name="duplicate-scene-{{ $scene->id }}"
        :action="route('scenes.duplicate', $scene)"
        :title="__('Duplicate Scene?')"
        :suggestion="$duplicateSuggestion"
    />
</x-app-layout>

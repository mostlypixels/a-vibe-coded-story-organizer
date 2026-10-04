<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1" class="flex items-center gap-2">
                {{ $numbering->sceneLabel($scene) }}
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
            <x-card :title="__('Description')" icon="tabler-align-left">
                <x-rich-text :html="$scene->description" />
            </x-card>
        @endif

        @if (filled($scene->contents))
            <x-card :title="__('Prose')" icon="tabler-feather">
                <x-scene-prose :scene="$scene" />
            </x-card>
        @endif

        @if (filled($scene->notes))
            <x-card :title="__('Notes')" icon="tabler-note">
                <x-rich-text :html="$scene->notes" />
            </x-card>
        @endif

        @if ($scene->event)
            <x-card :title="__('Happens during')" icon="tabler-calendar-event">
                <a href="{{ route('events.show', $scene->event) }}" class="text-link hover:text-link-hover">{{ $scene->event->title }}</a>
                <span class="text-sm text-content-muted"> &middot; <x-date :value="$scene->event->event_datetime" with-time /></span>
            </x-card>
        @endif

        @if ($scene->mentionedEvents->isNotEmpty())
            <x-card :title="__('Mentions')" icon="tabler-at">
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
            <x-card :title="__('Codex references')" icon="entity-codex">
                @include('codex.partials.referenced-entries', ['referencedEntries' => $referencedEntries, 'scene' => $scene])
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

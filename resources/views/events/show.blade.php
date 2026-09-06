<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1" class="flex items-center gap-2">
                {{ $event->title }}
                @if ($event->is_fixed)
                    <x-badge>{{ __('Fixed') }}</x-badge>
                @endif
            </x-heading>
            <p class="text-sm text-content-muted"><x-date :value="$event->event_datetime" with-time /></p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('events.edit', $event)" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'event', 'id' => $event->id]) }}" />
            @unless ($event->is_fixed)
                <x-icon-delete-button :action="route('events.destroy', $event)" :confirm="__('Are you sure you want to delete this event?')" />
            @endunless
        </div>
    </div>

    <div class="space-y-6">
        @if (filled($event->description))
            <x-card :title="__('Description')">
                <x-rich-text :html="$event->description" />
            </x-card>
        @endif

        @if ($event->plotlines->isNotEmpty())
            <x-card :title="__('Plotlines')">
                <div class="flex flex-wrap items-center gap-3">
                    @foreach ($event->plotlines as $plotline)
                        <a href="{{ route('plotlines.show', $plotline) }}" class="inline-flex items-center gap-1 text-sm text-link hover:text-link-hover">
                            <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                            {{ $plotline->name }}
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endif

        @if ($scenesOnEvent->isNotEmpty())
            <x-card :title="__('Scenes')">
                <div x-data="{ showAll: false }">
                    <x-table>
                        <x-slot:head>
                            <x-table-heading>{{ __('Scene') }}</x-table-heading>
                            <x-table-heading>{{ __('Chapter') }}</x-table-heading>
                            <x-table-heading>{{ __('Act') }}</x-table-heading>
                        </x-slot:head>

                        @foreach ($scenesOnEvent as $scene)
                            <x-table-row :striped="$loop->even" x-show="{{ $loop->index < 20 ? 'true' : 'showAll' }}">
                                <x-table-cell>
                                    <a href="{{ route('scenes.show', $scene) }}" class="text-link hover:text-link-hover">{{ $scene->name }}</a>
                                </x-table-cell>
                                <x-table-cell muted>{{ $scene->chapter->name }}</x-table-cell>
                                <x-table-cell muted>{{ $scene->chapter->act->name }}</x-table-cell>
                            </x-table-row>
                        @endforeach
                    </x-table>

                    @if ($scenesOnEvent->count() > 20)
                        <button type="button" x-show="! showAll" x-on:click="showAll = true" class="mt-2 text-sm text-link hover:text-link-hover">
                            {{ __('Show all :count', ['count' => $scenesOnEvent->count()]) }}
                        </button>
                    @endif
                </div>
            </x-card>
        @endif

        @if ($mentioningScenes->isNotEmpty())
            <x-card :title="__('Scenes mentioning this event')">
                <div x-data="{ showAll: false }">
                    <x-table>
                        <x-slot:head>
                            <x-table-heading>{{ __('Scene') }}</x-table-heading>
                            <x-table-heading>{{ __('Chapter') }}</x-table-heading>
                            <x-table-heading>{{ __('Act') }}</x-table-heading>
                        </x-slot:head>

                        @foreach ($mentioningScenes as $scene)
                            <x-table-row :striped="$loop->even" x-show="{{ $loop->index < 20 ? 'true' : 'showAll' }}">
                                <x-table-cell>
                                    <a href="{{ route('scenes.show', $scene) }}" class="text-link hover:text-link-hover">{{ $scene->name }}</a>
                                </x-table-cell>
                                <x-table-cell muted>{{ $scene->chapter->name }}</x-table-cell>
                                <x-table-cell muted>{{ $scene->chapter->act->name }}</x-table-cell>
                            </x-table-row>
                        @endforeach
                    </x-table>

                    @if ($mentioningScenes->count() > 20)
                        <button type="button" x-show="! showAll" x-on:click="showAll = true" class="mt-2 text-sm text-link hover:text-link-hover">
                            {{ __('Show all :count', ['count' => $mentioningScenes->count()]) }}
                        </button>
                    @endif
                </div>
            </x-card>
        @endif

        @if ($lifespanEntries['inceptions']->isNotEmpty() || $lifespanEntries['terminations']->isNotEmpty())
            <x-card :title="__('Codex entries')">
                <div class="space-y-4">
                    @if ($lifespanEntries['inceptions']->isNotEmpty())
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-content-muted">{{ __('Begins here') }}</h4>
                            <ul class="space-y-1 text-sm">
                                @foreach ($lifespanEntries['inceptions'] as $entry)
                                    <li><a href="{{ route('codex.show', $entry) }}" class="text-link hover:text-link-hover">{{ $entry->name }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($lifespanEntries['terminations']->isNotEmpty())
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-content-muted">{{ __('Ends here') }}</h4>
                            <ul class="space-y-1 text-sm">
                                @foreach ($lifespanEntries['terminations'] as $entry)
                                    <li><a href="{{ route('codex.show', $entry) }}" class="text-link hover:text-link-hover">{{ $entry->name }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>

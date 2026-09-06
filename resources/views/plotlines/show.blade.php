<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1" class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                {{ $plotline->name }}
                @if ($plotline->is_main)
                    <x-badge>{{ __('Main') }}</x-badge>
                @endif
            </x-heading>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('plotlines.edit', $plotline)" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'plotline', 'id' => $plotline->id]) }}" />
            @unless ($plotline->is_main)
                <x-icon-delete-button :action="route('plotlines.destroy', $plotline)" :confirm="__('Are you sure you want to delete this plotline?')" />
            @endunless
        </div>
    </div>

    <div class="space-y-6">
        @if (filled($plotline->description))
            <x-card :title="__('Description')">
                <x-rich-text :html="$plotline->description" />
            </x-card>
        @endif

        @if ($plotline->events->isNotEmpty())
            <x-card :title="__('Events')">
                <x-table>
                    <x-slot:head>
                        <x-table-heading>{{ __('Title') }}</x-table-heading>
                        <x-table-heading>{{ __('Date') }}</x-table-heading>
                    </x-slot:head>

                    @foreach ($plotline->events as $event)
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
    </div>
</x-app-layout>

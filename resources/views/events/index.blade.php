<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Events') }}
    </x-page-heading>

    <div class="space-y-6">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by title...')"
                :clear-url="route('projects.events.index', $project)"
                :create-url="route('projects.events.create', $project)"
                :create-label="__('New Event')"
                :filters="['search', 'plotline']"
            >
                <x-select name="plotline" class="text-sm">
                    <option value="">{{ __('All plotlines') }}</option>
                    @foreach ($project->plotlines as $plotline)
                        <option value="{{ $plotline->id }}" @selected(request('plotline') == $plotline->id)>{{ $plotline->name }}</option>
                    @endforeach
                </x-select>
            </x-index-toolbar>

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="title" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-sortable-header field="event_datetime" :sort="$sort" :direction="$direction">{{ __('Date') }}</x-sortable-header>
                    <x-table-heading>{{ __('Plotlines') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($events as $event)
                    <x-table-row :striped="$loop->even">
                        <x-table-cell>
                            <a href="{{ route('events.show', $event) }}" class="flex items-center gap-2 font-semibold text-content hover:text-link">
                                {{ $event->title }}
                                @if ($event->is_fixed)
                                    <x-badge>{{ __('Fixed') }}</x-badge>
                                @endif
                            </a>
                            @if ($event->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$event->description" /></div>
                            @endif
                        </x-table-cell>
                        <x-table-cell muted nowrap><x-date :value="$event->event_datetime" with-time /></x-table-cell>
                        <x-table-cell sm class="text-content-subtle">
                            <div class="flex items-center gap-3 flex-wrap">
                                @foreach ($event->plotlines as $plotline)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                                        {{ $plotline->name }}
                                    </span>
                                @endforeach
                            </div>
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-view-link :href="route('events.show', $event)" />
                                <x-icon-edit-link :href="route('events.edit', $event)" />
                                @unless ($event->is_fixed)
                                    <x-icon-delete-button :action="route('events.destroy', $event)" :confirm="__('Are you sure you want to delete this event?')" />
                                @endunless
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="4"
                        :filtered="request()->hasAny(['search', 'plotline'])"
                        :create-url="route('projects.events.create', $project)"
                        :create-label="__('New Event')"
                        :items="__('events')"
                    />
                @endforelse
            </x-table>
    </div>
</x-app-layout>

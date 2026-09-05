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
                        <td class="px-4 py-3">
                            <a href="{{ route('events.edit', $event) }}" class="flex items-center gap-2 font-semibold text-content hover:text-link">
                                {{ $event->title }}
                                @if ($event->is_fixed)
                                    <x-badge>{{ __('Fixed') }}</x-badge>
                                @endif
                            </a>
                            @if ($event->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$event->description" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-content-muted"><x-date :value="$event->event_datetime" with-time /></td>
                        <td class="px-4 py-3 text-sm text-content-subtle">
                            <div class="flex items-center gap-3 flex-wrap">
                                @foreach ($event->plotlines as $plotline)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                                        {{ $plotline->name }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-edit-link :href="route('events.edit', $event)" />
                                @unless ($event->is_fixed)
                                    <x-icon-delete-button :action="route('events.destroy', $event)" :confirm="__('Are you sure you want to delete this event?')" />
                                @endunless
                            </div>
                        </td>
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

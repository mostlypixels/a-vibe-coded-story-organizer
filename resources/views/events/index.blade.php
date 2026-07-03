<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Events') }}
            </h2>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by title...') }}" class="text-sm" :value="request('search')" />

                    <select name="plotline" class="text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">
                        <option value="">{{ __('All plotlines') }}</option>
                        @foreach ($project->plotlines as $plotline)
                            <option value="{{ $plotline->id }}" @selected(request('plotline') == $plotline->id)>{{ $plotline->name }}</option>
                        @endforeach
                    </select>

                    <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                    @if (request()->filled('search') || request()->filled('plotline'))
                        <a href="{{ route('projects.events.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.events.create', $project)">{{ __('New Event') }}</x-button>
            </div>

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
                            <div class="flex items-center gap-2 font-semibold text-gray-800">
                                {{ $event->title }}
                                @if ($event->is_fixed)
                                    <x-badge>{{ __('Fixed') }}</x-badge>
                                @endif
                            </div>
                            @if ($event->description)
                                <div class="mt-1 text-sm text-gray-500">{{ $event->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $event->event_datetime->format('M j, Y g:i A') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-400">
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
                    <x-table-empty :colspan="4">{{ __('No events match.') }}</x-table-empty>
                @endforelse
            </x-table>
        </div>
    </div>
</x-app-layout>

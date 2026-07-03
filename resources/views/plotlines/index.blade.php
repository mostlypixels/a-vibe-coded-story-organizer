<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Plotlines') }}
            </h2>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name...') }}" class="text-sm" :value="request('search')" />
                    <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                    @if (request()->filled('search'))
                        <a href="{{ route('projects.plotlines.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.plotlines.create', $project)">{{ __('New Plotline') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header>
                    <x-table-heading>{{ __('Description') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($plotlines as $plotline)
                    <x-table-row :striped="$loop->even">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-semibold text-gray-800 flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                                {{ $plotline->name }}
                                @if ($plotline->is_main)
                                    <x-badge>{{ __('Main') }}</x-badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $plotline->description }}</td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-edit-link :href="route('plotlines.edit', $plotline)" />
                                @unless ($plotline->is_main)
                                    <x-icon-delete-button :action="route('plotlines.destroy', $plotline)" :confirm="__('Are you sure you want to delete this plotline?')" />
                                @endunless
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty :colspan="3">{{ __('No plotlines match.') }}</x-table-empty>
                @endforelse
            </x-table>
        </div>
    </div>
</x-app-layout>

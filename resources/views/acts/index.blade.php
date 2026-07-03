<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Acts') }}
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
                        <a href="{{ route('projects.acts.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.acts.create', $project)">{{ __('New Act') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="position" :sort="$sort" :direction="$direction">{{ __('#') }}</x-sortable-header>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Chapters') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($acts as $act)
                    <x-table-row :striped="$loop->even">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $act->position }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800">{{ $act->name }}</div>
                            @if ($act->description)
                                <div class="mt-1 text-sm text-gray-500">{{ $act->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $act->chapters_count }}</td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position')
                                    <x-icon-move-up-button :action="route('acts.move-up', $act)" :disabled="$loop->first" />
                                    <x-icon-move-down-button :action="route('acts.move-down', $act)" :disabled="$loop->last" />
                                @endif
                                <x-icon-edit-link :href="route('acts.edit', $act)" />
                                <x-icon-delete-button :action="route('acts.destroy', $act)" :confirm="__('Are you sure you want to delete this act?')" />
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty :colspan="4">{{ __('No acts match.') }}</x-table-empty>
                @endforelse
            </x-table>
        </div>
    </div>
</x-app-layout>

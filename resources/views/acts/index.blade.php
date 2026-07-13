<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ $project->name }} &mdash; {{ __('Acts') }}
            </x-heading>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name...') }}" class="text-sm" :value="request('search')" />
                    <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>
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
                            <a href="{{ route('acts.edit', $act) }}" class="font-semibold text-gray-800 hover:text-ocean-600">{{ $act->name }}</a>
                            @if ($act->description)
                                <div class="mt-1 text-sm text-gray-500"><x-rich-text-excerpt :html="$act->description" /></div>
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
                    <x-table-empty
                        :colspan="4"
                        :filtered="request()->filled('search')"
                        :create-url="route('projects.acts.create', $project)"
                        :create-label="__('New Act')"
                        :items="__('acts')"
                    />
                @endforelse
            </x-table>
    </div>
</x-app-layout>

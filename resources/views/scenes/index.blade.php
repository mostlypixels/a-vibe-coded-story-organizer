<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Scenes') }}
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
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name...') }}" class="text-sm" :value="request('search')" />

                    <select name="chapter" class="text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">
                        <option value="">{{ __('All chapters') }}</option>
                        @foreach ($chapters as $chapter)
                            <option value="{{ $chapter->id }}" @selected(request('chapter') == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                        @endforeach
                    </select>

                    <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                    @if (request()->filled('search') || request()->filled('chapter'))
                        <a href="{{ route('projects.scenes.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.scenes.create', $project)">{{ __('New Scene') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-table-heading>{{ __('#') }}</x-table-heading>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Chapter') }}</x-table-heading>
                    <x-table-heading>{{ __('Description') }}</x-table-heading>
                    <x-table-heading>{{ __('Status') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($scenes as $scene)
                    <x-table-row :striped="$loop->even">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $scene->position }}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-gray-800">{{ $scene->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $scene->chapter->act->name }} &mdash; {{ $scene->chapter->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $scene->description }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-scene-status-badge :status="$scene->status" /></td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position' && request()->filled('chapter'))
                                    <x-icon-move-up-button :action="route('scenes.move-up', $scene)" :disabled="$loop->first" />
                                    <x-icon-move-down-button :action="route('scenes.move-down', $scene)" :disabled="$loop->last" />
                                @endif
                                <x-icon-edit-link :href="route('scenes.edit', $scene)" />
                                <x-icon-delete-button :action="route('scenes.destroy', $scene)" :confirm="__('Are you sure you want to delete this scene?')" />
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty :colspan="6">{{ __('No scenes match.') }}</x-table-empty>
                @endforelse
            </x-table>
        </div>
    </div>
</x-app-layout>

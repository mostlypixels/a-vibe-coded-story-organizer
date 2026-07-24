<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ $project->name }} &mdash; {{ __('Chapters') }}
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

                    <select name="act" class="text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">
                        <option value="">{{ __('All acts') }}</option>
                        @foreach ($project->acts as $act)
                            <option value="{{ $act->id }}" @selected(request('act') == $act->id)>{{ $act->name }}</option>
                        @endforeach
                    </select>

                    <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>
                    @if (request()->filled('search') || request()->filled('act'))
                        <a href="{{ route('projects.chapters.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.chapters.create', $project)">{{ __('New Chapter') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="position" :sort="$sort" :direction="$direction">{{ __('#') }}</x-sortable-header>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Act') }}</x-table-heading>
                    <x-table-heading>{{ __('Scenes') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($chapters as $chapter)
                    <x-table-row :striped="$loop->even">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $chapter->position }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('chapters.edit', $chapter) }}" class="font-semibold text-gray-800 hover:text-ocean-600">{{ $chapter->name }}</a>
                            @if ($chapter->description)
                                <div class="mt-1 text-sm text-gray-500"><x-rich-text-excerpt :html="$chapter->description" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $chapter->act->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $chapter->scenes_count }}</td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position' && request()->filled('act'))
                                    <x-icon-move-up-button :action="route('chapters.move-up', $chapter)" :disabled="$loop->first" />
                                    <x-icon-move-down-button :action="route('chapters.move-down', $chapter)" :disabled="$loop->last" />
                                @endif
                                <x-icon-edit-link :href="route('chapters.edit', $chapter)" />
                                @if ($chapter->scenes_count > 0)
                                    {{-- Chapter has scenes: open the "move or delete" dialog instead of a plain confirm(). --}}
                                    <button
                                        type="button"
                                        x-data=""
                                        x-on:click="$dispatch('open-modal', 'delete-chapter-{{ $chapter->id }}')"
                                        class="inline-flex items-center justify-center p-1.5 rounded-md border border-red-600 bg-transparent text-red-600 hover:bg-red-50"
                                        title="{{ __('Delete') }}"
                                    >
                                        <span class="sr-only">{{ __('Delete') }}</span>
                                        <x-tabler-trash class="h-4 w-4" />
                                    </button>
                                @else
                                    <x-icon-delete-button :action="route('chapters.destroy', $chapter)" :confirm="__('Are you sure you want to delete this chapter?')" />
                                @endif
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="5"
                        :filtered="request()->hasAny(['search', 'act'])"
                        :create-url="route('projects.chapters.create', $project)"
                        :create-label="__('New Chapter')"
                        :items="__('chapters')"
                    />
                @endforelse
            </x-table>

            {{-- Delete-with-move dialogs live outside the table (a modal <div> is not
                 valid inside a <tbody>); each is opened by its row's trash button. --}}
            @foreach ($chapters as $chapter)
                @if ($chapter->scenes_count > 0)
                    <x-delete-with-move-dialog
                        name="delete-chapter-{{ $chapter->id }}"
                        :action="route('chapters.destroy', $chapter)"
                        :title="__('Delete Chapter?')"
                        :child-count="$chapter->scenes_count"
                        child-singular="scene"
                        child-plural="scenes"
                        destination-noun="chapter"
                        :destinations="$destinationChapters->where('id', '!=', $chapter->id)->values()"
                    />
                @endif
            @endforeach
    </div>
</x-app-layout>

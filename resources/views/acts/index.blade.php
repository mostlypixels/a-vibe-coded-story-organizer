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
                                @if ($act->chapters_count > 0)
                                    {{-- Act has chapters: open the "move or delete" dialog instead of a plain confirm(). --}}
                                    <button
                                        type="button"
                                        x-data=""
                                        x-on:click="$dispatch('open-modal', 'delete-act-{{ $act->id }}')"
                                        class="inline-flex items-center justify-center p-1.5 rounded-md border border-red-600 bg-transparent text-red-600 hover:bg-red-50"
                                        title="{{ __('Delete') }}"
                                    >
                                        <span class="sr-only">{{ __('Delete') }}</span>
                                        <x-tabler-trash class="h-4 w-4" />
                                    </button>
                                @else
                                    <x-icon-delete-button :action="route('acts.destroy', $act)" :confirm="__('Are you sure you want to delete this act?')" />
                                @endif
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

            {{-- Delete-with-move dialogs live outside the table (a modal <div> is not
                 valid inside a <tbody>); each is opened by its row's trash button. --}}
            @foreach ($acts as $act)
                @if ($act->chapters_count > 0)
                    <x-delete-with-move-dialog
                        name="delete-act-{{ $act->id }}"
                        :action="route('acts.destroy', $act)"
                        :title="__('Delete Act?')"
                        :child-count="$act->chapters_count"
                        child-singular="chapter"
                        child-plural="chapters"
                        destination-noun="act"
                        :destinations="$destinationActs->where('id', '!=', $act->id)->values()"
                    />
                @endif
            @endforeach
    </div>
</x-app-layout>

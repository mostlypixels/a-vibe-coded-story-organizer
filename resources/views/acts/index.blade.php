<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ $project->name }} &mdash; {{ __('Acts') }}
            </x-heading>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-content-muted hover:text-content">
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
                        <a href="{{ route('projects.acts.index', $project) }}" class="text-sm text-content-muted hover:text-content">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.acts.create', $project)">{{ __('New Act') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="position" :sort="$sort" :direction="$direction">{{ __('#') }}</x-sortable-header>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Chapters') }}</x-table-heading>
                    <x-table-heading class="text-right">{{ __('Words') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($acts as $act)
                    <x-table-row :striped="$loop->even">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-content-muted">{{ $act->position }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('acts.edit', $act) }}" class="font-semibold text-content hover:text-link">{{ $act->name }}</a>
                            @if ($act->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$act->description" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-content-muted">{{ $act->chapters_count }}</td>
                        <td class="px-4 py-3 text-right text-sm text-content-muted whitespace-nowrap">
                            <x-word-count :count="$act->word_count" variant="inline" />
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position')
                                    <x-icon-move-button direction="up" :action="route('acts.move-up', $act)" :disabled="$loop->first" />
                                    <x-icon-move-button direction="down" :action="route('acts.move-down', $act)" :disabled="$loop->last" />
                                @endif
                                <x-icon-edit-link :href="route('acts.edit', $act)" />
                                @if ($act->chapters_count > 0)
                                    {{-- Act has chapters: open the "move or delete" dialog instead of a plain confirm(). --}}
                                    <x-icon-dialog-button :modal="'delete-act-'.$act->id" />
                                @else
                                    <x-icon-delete-button :action="route('acts.destroy', $act)" :confirm="__('Are you sure you want to delete this act?')" />
                                @endif
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="5"
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

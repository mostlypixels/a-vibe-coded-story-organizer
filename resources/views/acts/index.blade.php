<x-app-layout>
    <x-page-heading>
        {{ $book->displayName() }} &mdash; {{ __('Acts') }}
    </x-page-heading>

    <div class="space-y-6">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by name...')"
                :clear-url="route('books.acts.index', $book)"
                :create-url="route('books.acts.create', $book)"
                :create-label="__('New Act')"
            />

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
                        <x-table-cell muted nowrap>{{ $numbering->act($act) }}</x-table-cell>
                        <x-table-cell>
                            <a href="{{ route('acts.edit', $act) }}" class="font-semibold text-content hover:text-link">{{ $act->name }}</a>
                            @if ($act->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$act->description" /></div>
                            @endif
                        </x-table-cell>
                        <x-table-cell muted>{{ $act->chapters_count }}</x-table-cell>
                        <x-table-cell align="right" muted nowrap>
                            <x-word-count :count="$act->word_count" variant="inline" />
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position')
                                    <x-icon-move-button direction="up" :action="route('acts.move-up', $act)" :disabled="$loop->first" />
                                    <x-icon-move-button direction="down" :action="route('acts.move-down', $act)" :disabled="$loop->last" />
                                @endif
                                <x-icon-edit-link :href="route('acts.edit', $act)" />
                                @if ($act->chapters_count > 0)
                                    <x-icon-dialog-button :modal="'delete-act-'.$act->id" />
                                @else
                                    <x-icon-delete-button :action="route('acts.destroy', $act)" :confirm="__('Are you sure you want to delete this act?')" />
                                @endif
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="5"
                        :filtered="request()->filled('search')"
                        :create-url="route('books.acts.create', $book)"
                        :create-label="__('New Act')"
                        :items="__('acts')"
                    />
                @endforelse

                @if ($acts->isNotEmpty())
                    <x-slot:foot>
                        <x-table-cell colspan="2" total>{{ __('Total') }}</x-table-cell>
                        <x-table-cell total>{{ $acts->sum('chapters_count') }}</x-table-cell>
                        <x-table-cell align="right" total nowrap>
                            <x-word-count :count="$acts->sum('word_count')" variant="inline" />
                        </x-table-cell>
                        <x-table-cell></x-table-cell>
                    </x-slot:foot>
                @endif
            </x-table>

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

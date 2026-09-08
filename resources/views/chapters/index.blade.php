<x-app-layout>
    <x-page-heading>
        {{ $book->displayName() }} &mdash; {{ __('Chapters') }}
    </x-page-heading>

    <div class="space-y-6">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by name...')"
                :clear-url="route('books.chapters.index', $book)"
                :create-url="route('books.chapters.create', $book)"
                :create-label="__('New Chapter')"
                :filters="['search', 'act']"
            >
                {{-- The form's default button: it submits without `jump`, so the Enter
                     key in the search box still filters instead of jumping. --}}
                <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                <x-select name="act" class="text-sm">
                    <option value="">{{ __('All acts') }}</option>
                    @foreach ($acts as $act)
                        <option value="{{ $act->id }}" @selected(request('act') == $act->id)>{{ $act->name }}</option>
                    @endforeach
                </x-select>

                <x-button variant="secondary" type="submit" name="jump" value="1">{{ __('Go to') }}</x-button>
            </x-index-toolbar>

            @php
                // The jumped-to act, and the first of its rows on this page: that
                // row carries the `#act-<id>` anchor the redirect points at, and an
                // id must stay unique in the document.
                $highlightId = filled(request('highlight')) ? (int) request('highlight') : null;
                $anchorChapterId = $highlightId ? $chapters->firstWhere('act_id', $highlightId)?->id : null;
            @endphp

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="position" :sort="$sort" :direction="$direction">{{ __('#') }}</x-sortable-header>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Act') }}</x-table-heading>
                    <x-table-heading>{{ __('Scenes') }}</x-table-heading>
                    <x-table-heading class="text-right">{{ __('Words') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($chapters as $chapter)
                    @php
                        $highlighted = $highlightId !== null && $chapter->act_id === $highlightId;
                    @endphp
                    <x-table-row :striped="$loop->even" :highlighted="$highlighted" :id="$chapter->id === $anchorChapterId ? 'act-'.$highlightId : null">
                        <x-table-cell muted nowrap class="{{ $highlighted ? 'border-l-4 border-accent' : '' }}">{{ $numbering->chapter($chapter) }}</x-table-cell>
                        <x-table-cell>
                            <a href="{{ route('chapters.show', $chapter) }}" class="font-semibold text-content hover:text-link">{{ $chapter->name }}</a>
                            @if ($chapter->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$chapter->description" /></div>
                            @endif
                        </x-table-cell>
                        <x-table-cell muted>{{ $chapter->act->name }}</x-table-cell>
                        <x-table-cell muted>{{ $chapter->scenes_count }}</x-table-cell>
                        <x-table-cell align="right" muted nowrap>
                            <x-word-count :count="$chapter->word_count" variant="inline" />
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position' && request()->filled('act'))
                                    <x-icon-move-button direction="up" :action="route('chapters.move-up', $chapter)" :disabled="$loop->first && $chapters->onFirstPage()" />
                                    <x-icon-move-button direction="down" :action="route('chapters.move-down', $chapter)" :disabled="$loop->last && $chapters->onLastPage()" />
                                @endif
                                <x-icon-view-link :href="route('chapters.show', $chapter)" />
                                <x-icon-edit-link :href="route('chapters.edit', $chapter)" />
                                @if ($chapter->scenes_count > 0)
                                    <x-icon-dialog-button :modal="'delete-chapter-'.$chapter->id" />
                                @else
                                    <x-icon-delete-button :action="route('chapters.destroy', $chapter)" :confirm="__('Are you sure you want to delete this chapter?')" />
                                @endif
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="6"
                        :filtered="request()->hasAny(['search', 'act'])"
                        :create-url="route('books.chapters.create', $book)"
                        :create-label="__('New Chapter')"
                        :items="__('chapters')"
                    />
                @endforelse

                @if ($chapters->isNotEmpty())
                    <x-slot:foot>
                        <tr>
                            <x-table-cell colspan="3" total>{{ __('Page total') }}</x-table-cell>
                            <x-table-cell total>{{ $chapters->sum('scenes_count') }}</x-table-cell>
                            <x-table-cell align="right" total nowrap>
                                <x-word-count :count="$chapters->sum('word_count')" variant="inline" />
                            </x-table-cell>
                            <x-table-cell></x-table-cell>
                        </tr>
                        <tr>
                            <x-table-cell colspan="3" total>{{ __('Full total') }}</x-table-cell>
                            <x-table-cell total>{{ $fullSceneCount }}</x-table-cell>
                            <x-table-cell align="right" total nowrap>
                                <x-word-count :count="$fullWordCount" variant="inline" />
                            </x-table-cell>
                            <x-table-cell></x-table-cell>
                        </tr>
                    </x-slot:foot>
                @endif
            </x-table>

            <x-pagination-bar :paginator="$chapters" :range="$pageRange" />

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

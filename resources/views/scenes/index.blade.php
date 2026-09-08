<x-app-layout>
    <x-page-heading>
        {{ $book->displayName() }} &mdash; {{ __('Scenes') }}
    </x-page-heading>

    <div class="space-y-6">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by name...')"
                :clear-url="route('books.scenes.index', $book)"
                :create-url="route('books.scenes.create', $book)"
                :create-label="__('New Scene')"
                :filters="['search', 'chapter']"
            >
                {{-- The form's default button: it submits without `jump`, so the Enter
                     key in the search box still filters instead of jumping. --}}
                <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                <x-select name="chapter" class="text-sm">
                    <option value="">{{ __('All chapters') }}</option>
                    @foreach ($chapters->groupBy('act_id') as $actChapters)
                        <optgroup label="{{ $actChapters->first()->act->name }}">
                            @foreach ($actChapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected(request('chapter') == $chapter->id)>{{ $chapter->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-select>

                <x-button variant="secondary" type="submit" name="jump" value="1">{{ __('Go to') }}</x-button>
            </x-index-toolbar>

            @php
                // The jumped-to chapter, and the first of its rows on this page: that
                // row carries the `#chapter-<id>` anchor the redirect points at, and an
                // id must stay unique in the document.
                $highlightId = filled(request('highlight')) ? (int) request('highlight') : null;
                $anchorSceneId = $highlightId ? $scenes->firstWhere('chapter_id', $highlightId)?->id : null;
            @endphp

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="position" :sort="$sort" :direction="$direction">{{ __('#') }}</x-sortable-header>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Chapter') }}</x-table-heading>
                    <x-table-heading>{{ __('In chapter') }}</x-table-heading>
                    <x-table-heading>{{ __('Status') }}</x-table-heading>
                    <x-table-heading>{{ __('Event') }}</x-table-heading>
                    <x-table-heading class="text-right">{{ __('Words') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($scenes as $scene)
                    @php
                        $highlighted = $highlightId !== null && $scene->chapter_id === $highlightId;
                        // The missing-event marker wins: it reports a problem in the data,
                        // while the highlight only reports where you landed.
                        $markerClass = match (true) {
                            ! $scene->event => 'border-l-4 border-danger',
                            $highlighted => 'border-l-4 border-accent',
                            default => '',
                        };
                    @endphp
                    <x-table-row :striped="$loop->even" :highlighted="$highlighted" :id="$scene->id === $anchorSceneId ? 'chapter-'.$highlightId : null">
                        <x-table-cell :title="$scene->event ? null : __('This scene has no “happens during” event yet.')" muted nowrap class="{{ $markerClass }}">{{ $numbering->scene($scene) }}</x-table-cell>
                        <x-table-cell>
                            <a href="{{ route('scenes.show', $scene) }}" class="font-semibold text-content hover:text-link">{{ $scene->name }}</a>
                            @if ($scene->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$scene->description" /></div>
                            @endif
                        </x-table-cell>
                        <x-table-cell muted>{{ $scene->chapter->act->name }} &mdash; {{ $scene->chapter->name }}</x-table-cell>
                        <x-table-cell muted>{{ $scene->position }}</x-table-cell>
                        <x-table-cell nowrap><x-scene-status-badge :status="$scene->status" /></x-table-cell>
                        <x-table-cell nowrap sm>
                            @if ($scene->event)
                                <span class="text-content-muted">{{ $scene->event->title }}</span>
                            @else
                                <span title="{{ __('This scene has no “happens during” event yet.') }}" class="inline-flex items-center rounded-md border border-danger px-2 py-0.5 text-xs font-medium text-danger-surface-content">{{ __('Unassigned') }}</span>
                            @endif
                        </x-table-cell>
                        <x-table-cell align="right" muted nowrap>
                            <x-word-count :count="$scene->word_count" variant="inline" />
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position' && request()->filled('chapter'))
                                    <x-icon-move-button direction="up" :action="route('scenes.move-up', $scene)" :disabled="$loop->first && $scenes->onFirstPage()" />
                                    <x-icon-move-button direction="down" :action="route('scenes.move-down', $scene)" :disabled="$loop->last && $scenes->onLastPage()" />
                                @endif
                                <x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-scene-'.$scene->id" :label="__('Duplicate')" />
                                <x-icon-view-link :href="route('scenes.show', $scene)" />
                                <x-icon-edit-link :href="route('scenes.edit', $scene)" />
                                <x-icon-delete-button :action="route('scenes.destroy', $scene)" :confirm="__('Are you sure you want to delete this scene?')" />
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="8"
                        :filtered="request()->hasAny(['search', 'chapter'])"
                        :create-url="route('books.scenes.create', $book)"
                        :create-label="__('New Scene')"
                        :items="__('scenes')"
                    />
                @endforelse

                @if ($scenes->isNotEmpty())
                    <x-slot:foot>
                        <tr>
                            <x-table-cell colspan="6" total>{{ __('Page total') }}</x-table-cell>
                            <x-table-cell align="right" total nowrap>
                                <x-word-count :count="$scenes->sum('word_count')" variant="inline" />
                            </x-table-cell>
                            <x-table-cell></x-table-cell>
                        </tr>
                        <tr>
                            <x-table-cell colspan="6" total>{{ __('Full total') }}</x-table-cell>
                            <x-table-cell align="right" total nowrap>
                                <x-word-count :count="$fullWordCount" variant="inline" />
                            </x-table-cell>
                            <x-table-cell></x-table-cell>
                        </tr>
                    </x-slot:foot>
                @endif
            </x-table>

            <x-pagination-bar :paginator="$scenes" :range="$pageRange" />

            @foreach ($scenes as $scene)
                <x-duplicate-dialog
                    name="duplicate-scene-{{ $scene->id }}"
                    :action="route('scenes.duplicate', $scene)"
                    :title="__('Duplicate Scene?')"
                    :suggestion="$duplicateNames[$scene->id]"
                />
            @endforeach
    </div>
</x-app-layout>

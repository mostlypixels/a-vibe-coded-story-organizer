<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Scenes') }}
    </x-page-heading>

    <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name...') }}" class="text-sm" :value="request('search')" />

                    <x-select name="chapter" class="text-sm">
                        <option value="">{{ __('All chapters') }}</option>
                        @foreach ($chapters as $chapter)
                            <option value="{{ $chapter->id }}" @selected(request('chapter') == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                        @endforeach
                    </x-select>

                    <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>
                    @if (request()->filled('search') || request()->filled('chapter'))
                        <a href="{{ route('projects.scenes.index', $project) }}" class="text-sm text-content-muted hover:text-content">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.scenes.create', $project)">{{ __('New Scene') }}</x-button>
            </div>

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
                    <x-table-row :striped="$loop->even">
                        <td @unless($scene->event) title="{{ __('This scene has no “happens during” event yet.') }}" @endunless class="px-4 py-3 whitespace-nowrap text-sm text-content-muted {{ $scene->event ? '' : 'border-l-4 border-danger' }}">{{ $numbering->scene($scene) }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('scenes.edit', $scene) }}" class="font-semibold text-content hover:text-link">{{ $scene->name }}</a>
                            @if ($scene->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$scene->description" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-content-muted">{{ $scene->chapter->act->name }} &mdash; {{ $scene->chapter->name }}</td>
                        <td class="px-4 py-3 text-sm text-content-muted">{{ $scene->position }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-scene-status-badge :status="$scene->status" /></td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            @if ($scene->event)
                                <span class="text-content-muted">{{ $scene->event->title }}</span>
                            @else
                                <span title="{{ __('This scene has no “happens during” event yet.') }}" class="inline-flex items-center rounded-md border border-danger px-2 py-0.5 text-xs font-medium text-danger-surface-content">{{ __('Unassigned') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-content-muted whitespace-nowrap">
                            <x-word-count :count="$scene->word_count" variant="inline" />
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @if ($sort === 'position' && request()->filled('chapter'))
                                    <x-icon-move-button direction="up" :action="route('scenes.move-up', $scene)" :disabled="$loop->first" />
                                    <x-icon-move-button direction="down" :action="route('scenes.move-down', $scene)" :disabled="$loop->last" />
                                @endif
                                <x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-scene-'.$scene->id" :label="__('Duplicate')" />
                                <x-icon-edit-link :href="route('scenes.edit', $scene)" />
                                <x-icon-delete-button :action="route('scenes.destroy', $scene)" :confirm="__('Are you sure you want to delete this scene?')" />
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="8"
                        :filtered="request()->hasAny(['search', 'chapter'])"
                        :create-url="route('projects.scenes.create', $project)"
                        :create-label="__('New Scene')"
                        :items="__('scenes')"
                    />
                @endforelse

                {{-- Totals of the rows shown (the filtered set when a search or chapter filter is active). --}}
                @if ($scenes->isNotEmpty())
                    <x-slot:foot>
                        <td colspan="6" class="px-4 py-3 text-sm font-semibold text-table-header-content">{{ __('Total') }}</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-table-header-content whitespace-nowrap">
                            <x-word-count :count="$scenes->sum('word_count')" variant="inline" />
                        </td>
                        <td class="px-4 py-3"></td>
                    </x-slot:foot>
                @endif
            </x-table>

            {{-- Duplicate-name dialogs live outside the table (a modal <div> is not
                 valid inside a <tbody>); each is opened by its row's copy button. --}}
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

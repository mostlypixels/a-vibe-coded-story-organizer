<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ $type->pluralLabel() }}
    </x-page-heading>

    <div class="space-y-6">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by name or alias…')"
                :clear-url="route('projects.codex.index', [$project, $type->routeKey()])"
                :create-url="route('projects.codex.create', [$project, $type->routeKey()])"
                :create-label="__('New :label', ['label' => $type->label()])"
                :filters="['search', 'tag']"
            >
                <x-select name="tag" class="text-sm">
                    <option value="">{{ __('All tags') }}</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(request('tag') == $tag->id)>{{ $tag->name }}</option>
                    @endforeach
                </x-select>
            </x-index-toolbar>

            <x-table>
                <x-slot:head>
                    <x-table-heading><span class="sr-only">{{ __('Cover') }}</span></x-table-heading>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header>
                    <x-table-heading>{{ __('Aliases') }}</x-table-heading>
                    <x-table-heading>{{ __('Tags') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($entries as $entry)
                    <x-table-row :striped="$loop->even">
                        <x-table-cell>
                            <x-cover-thumbnail :href="route('codex.show', $entry)" :src="$entry->cover?->url()" :alt="$entry->name" :icon="'entity-'.$type->value" :label="$entry->initials()" />
                        </x-table-cell>
                        <x-table-cell>
                            <a href="{{ route('codex.show', $entry) }}" class="font-semibold text-content hover:text-link">{{ $entry->name }}</a>
                        </x-table-cell>
                        <x-table-cell muted>
                            {{ $entry->aliases->pluck('alias')->join(', ') ?: '—' }}
                        </x-table-cell>
                        <x-table-cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($entry->tags as $tag)
                                    <x-badge>{{ $tag->name }}</x-badge>
                                @empty
                                    <span class="text-sm text-content-subtle">—</span>
                                @endforelse
                            </div>
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-duplicate-button dialog="duplicate-codex-entry" :action="route('codex.duplicate', $entry)" :suggestion="$duplicateNames[$entry->id]" />
                                <x-icon-edit-link :href="route('codex.edit', $entry)" />
                                <x-icon-delete-button :action="route('codex.destroy', $entry)" :confirm="__('Are you sure you want to delete this entry?')" />
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="5"
                        :filtered="request()->hasAny(['search', 'tag'])"
                        :create-url="route('projects.codex.create', [$project, $type->routeKey()])"
                        :create-label="__('New :label', ['label' => $type->label()])"
                        :items="\Illuminate\Support\Str::lower($type->pluralLabel())"
                    />
                @endforelse
            </x-table>

            <x-pagination-bar :paginator="$entries" />

            <x-duplicate-dialog name="duplicate-codex-entry" :title="__('Duplicate :label', ['label' => $type->label()])" />
    </div>
</x-app-layout>

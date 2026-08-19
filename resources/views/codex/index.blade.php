<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ $type->pluralLabel() }}
    </x-page-heading>

    <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name or alias…') }}" class="text-sm" :value="request('search')" />

                    <x-select name="tag" class="text-sm">
                        <option value="">{{ __('All tags') }}</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" @selected(request('tag') == $tag->id)>{{ $tag->name }}</option>
                        @endforeach
                    </x-select>

                    <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>
                    @if (request()->filled('search') || request()->filled('tag'))
                        <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-content-muted hover:text-content">{{ __('Clear') }}</a>
                    @endif
                </form>

                <x-button variant="primary" :href="route('projects.codex.create', [$project, $type->routeKey()])">{{ __('New :label', ['label' => $type->label()]) }}</x-button>
            </div>

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
                        <td class="px-4 py-3">
                            <a href="{{ route('codex.edit', $entry) }}">
                                @if ($entry->cover)
                                    <img src="{{ $entry->cover->url() }}" alt="{{ $entry->name }}" class="h-10 w-10 rounded-sm object-cover border border-border">
                                @else
                                    <div class="h-10 w-10 rounded-sm bg-surface border border-border" aria-hidden="true"></div>
                                @endif
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('codex.edit', $entry) }}" class="font-semibold text-content hover:text-link">{{ $entry->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-content-muted">
                            {{ $entry->aliases->pluck('alias')->join(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($entry->tags as $tag)
                                    <x-badge>{{ $tag->name }}</x-badge>
                                @empty
                                    <span class="text-sm text-content-subtle">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-codex-entry-'.$entry->id" :label="__('Duplicate')" />
                                <x-icon-edit-link :href="route('codex.edit', $entry)" />
                                <x-icon-delete-button :action="route('codex.destroy', $entry)" :confirm="__('Are you sure you want to delete this entry?')" />
                            </div>
                        </td>
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

            @foreach ($entries as $entry)
                <x-duplicate-dialog
                    name="duplicate-codex-entry-{{ $entry->id }}"
                    :action="route('codex.duplicate', $entry)"
                    :title="__('Duplicate :label', ['label' => $type->label()])"
                    :suggestion="$duplicateNames[$entry->id]"
                />
            @endforeach
    </div>
</x-app-layout>

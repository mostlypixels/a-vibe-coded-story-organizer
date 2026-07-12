<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ $type->pluralLabel() }}
            </h2>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name or alias…') }}" class="text-sm" :value="request('search')" />

                    <select name="tag" class="text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">
                        <option value="">{{ __('All tags') }}</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" @selected(request('tag') == $tag->id)>{{ $tag->name }}</option>
                        @endforeach
                    </select>

                    <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                    @if (request()->filled('search') || request()->filled('tag'))
                        <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
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
                            @if ($entry->cover)
                                <img src="{{ $entry->cover->url() }}" alt="{{ $entry->name }}" class="h-10 w-10 rounded object-cover border border-gray-200">
                            @else
                                <div class="h-10 w-10 rounded bg-gray-100 border border-gray-200" aria-hidden="true"></div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800">{{ $entry->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $entry->aliases->pluck('alias')->join(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($entry->tags as $tag)
                                    <x-badge>{{ $tag->name }}</x-badge>
                                @empty
                                    <span class="text-sm text-gray-400">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
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
    </div>
</x-app-layout>

<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Plotlines') }}
    </x-page-heading>

    <div class="space-y-6">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by name...')"
                :clear-url="route('projects.plotlines.index', $project)"
                :create-url="route('projects.plotlines.create', $project)"
                :create-label="__('New Plotline')"
            />

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($plotlines as $plotline)
                    <x-table-row :striped="$loop->even">
                        <x-table-cell>
                            <a href="{{ route('plotlines.show', $plotline) }}" class="font-semibold text-content hover:text-link flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                                {{ $plotline->name }}
                                @if ($plotline->is_main)
                                    <x-badge>{{ __('Main') }}</x-badge>
                                @endif
                            </a>
                            @if ($plotline->description)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$plotline->description" /></div>
                            @endif
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-view-link :href="route('plotlines.show', $plotline)" />
                                <x-icon-edit-link :href="route('plotlines.edit', $plotline)" />
                                @unless ($plotline->is_main)
                                    <x-icon-delete-button :action="route('plotlines.destroy', $plotline)" :confirm="__('Are you sure you want to delete this plotline?')" />
                                @endunless
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty :colspan="2">{{ __('No plotlines match.') }}</x-table-empty>
                @endforelse
            </x-table>
    </div>
</x-app-layout>

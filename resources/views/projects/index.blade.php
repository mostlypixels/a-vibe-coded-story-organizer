<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Projects') }}
        </x-heading>
    </x-slot>

    <div class="space-y-6" x-data="{ view: localStorage.getItem('dashboardProjectView') || 'list' }" x-effect="localStorage.setItem('dashboardProjectView', view)">
            <div class="flex items-center justify-between">
                <div class="inline-flex rounded-md shadow-xs" role="group" aria-label="{{ __('View') }}">
                    <button
                        type="button"
                        @click="view = 'list'"
                        :class="view === 'list' ? 'bg-accent-surface text-accent-content border-accent' : 'bg-surface-raised text-content-muted border-border-strong hover:bg-surface-sunken'"
                        class="inline-flex items-center gap-1.5 rounded-l-md border px-3 py-1.5 text-sm font-medium"
                    >
                        <x-tabler-layout-list class="h-4 w-4" aria-hidden="true" />
                        {{ __('List') }}
                    </button>
                    <button
                        type="button"
                        @click="view = 'grid'"
                        :class="view === 'grid' ? 'bg-accent-surface text-accent-content border-accent' : 'bg-surface-raised text-content-muted border-border-strong hover:bg-surface-sunken'"
                        class="-ml-px inline-flex items-center gap-1.5 rounded-r-md border px-3 py-1.5 text-sm font-medium"
                    >
                        <x-tabler-layout-grid class="h-4 w-4" aria-hidden="true" />
                        {{ __('Grid') }}
                    </button>
                </div>

                <x-button variant="primary" :href="route('projects.create')">{{ __('New Project') }}</x-button>
            </div>

            <div x-show="view === 'list'">
                <x-table>
                    <x-slot:head>
                        <x-table-heading><span class="sr-only">{{ __('Cover') }}</span></x-table-heading>
                        <x-table-heading>{{ __('Name') }}</x-table-heading>
                        <x-table-heading>{{ __('Description') }}</x-table-heading>
                        <x-table-heading />
                    </x-slot:head>

                    @forelse ($projects as $project)
                        <x-table-row :striped="$loop->even">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.edit', $project) }}">
                                    @if ($project->cover_image)
                                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($project->cover_image) }}" alt="{{ $project->name }}" class="h-10 w-10 rounded-sm object-cover border border-border">
                                    @else
                                        <div class="h-10 w-10 rounded-sm bg-surface border border-border" aria-hidden="true"></div>
                                    @endif
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.edit', $project) }}" class="font-semibold text-content hover:text-link">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-content-muted">
                                @if ($project->description)
                                    <x-rich-text-excerpt :html="$project->description" />
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <x-icon-edit-link :href="route('projects.edit', $project)" />
                                    <x-icon-delete-button
                                        :action="route('projects.destroy', $project)"
                                        :confirm="$deleteConfirms[$project->id]"
                                    />
                                </div>
                            </td>
                        </x-table-row>
                    @empty
                        <x-table-empty
                            :colspan="4"
                            :create-url="route('projects.create')"
                            :create-label="__('New Project')"
                            :items="__('projects')"
                        />
                    @endforelse
                </x-table>
            </div>

            <div x-show="view === 'grid'" style="display: none">
                @forelse ($projects as $project)
                    @if ($loop->first)
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @endif

                    <div class="overflow-hidden rounded-lg border border-border bg-surface-raised shadow-xs hover:shadow-md transition-shadow">
                        <a href="{{ route('projects.edit', $project) }}" class="block">
                            @if ($project->cover_image)
                                <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($project->cover_image) }}" alt="{{ $project->name }}" class="h-24 w-full object-cover">
                            @else
                                <div class="h-24 w-full bg-surface" aria-hidden="true"></div>
                            @endif
                            <div class="p-2">
                                <div class="text-sm font-semibold text-content truncate">{{ $project->name }}</div>
                                <div class="mt-0.5 text-xs text-content-muted line-clamp-2">
                                    @if ($project->description)
                                        <x-rich-text-excerpt :html="$project->description" :limit="60" />
                                    @else
                                        &mdash;
                                    @endif
                                </div>
                            </div>
                        </a>

                        <div class="flex items-center justify-end gap-1 border-t border-border px-2 py-1.5">
                            <x-icon-edit-link :href="route('projects.edit', $project)" />
                            <x-icon-delete-button
                                :action="route('projects.destroy', $project)"
                                :confirm="$deleteConfirms[$project->id]"
                            />
                        </div>
                    </div>

                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <x-card class="text-center text-content-muted">
                        {{ __('You have no projects yet.') }}
                    </x-card>
                @endforelse
            </div>
    </div>
</x-app-layout>

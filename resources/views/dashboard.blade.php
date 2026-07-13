<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    {{--
        The list/grid choice is a pure display preference, so it's kept client-side
        (Alpine + localStorage) rather than a query param — switching views never
        needs to hit the server or be linkable. Default is 'list'; the list markup
        below has no style="display:none" because it matches that default, while
        the grid markup does (mirrors the no-x-cloak convention documented in
        resources/views/components/wysiwyg.blade.php).
    --}}
    <div class="space-y-6" x-data="{ view: localStorage.getItem('dashboardProjectView') || 'list' }" x-effect="localStorage.setItem('dashboardProjectView', view)">
            <div class="flex items-center justify-between">
                <div class="inline-flex rounded-md shadow-sm" role="group" aria-label="{{ __('View') }}">
                    <button
                        type="button"
                        @click="view = 'list'"
                        :class="view === 'list' ? 'bg-ocean-50 text-ocean-600 border-ocean-500' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-50'"
                        class="inline-flex items-center gap-1.5 rounded-l-md border px-3 py-1.5 text-sm font-medium"
                    >
                        <x-tabler-layout-list class="h-4 w-4" aria-hidden="true" />
                        {{ __('List') }}
                    </button>
                    <button
                        type="button"
                        @click="view = 'grid'"
                        :class="view === 'grid' ? 'bg-ocean-50 text-ocean-600 border-ocean-500' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-50'"
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
                    </x-slot:head>

                    @forelse ($projects as $project)
                        <x-table-row :striped="$loop->even">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.edit', $project) }}">
                                    @if ($project->cover_image)
                                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($project->cover_image) }}" alt="{{ $project->name }}" class="h-10 w-10 rounded object-cover border border-gray-200">
                                    @else
                                        <div class="h-10 w-10 rounded bg-gray-100 border border-gray-200" aria-hidden="true"></div>
                                    @endif
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.edit', $project) }}" class="font-semibold text-gray-800 hover:text-ocean-600">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if ($project->description)
                                    <x-rich-text-excerpt :html="$project->description" />
                                @else
                                    &mdash;
                                @endif
                            </td>
                        </x-table-row>
                    @empty
                        <x-table-empty
                            :colspan="3"
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

                    <a href="{{ route('projects.edit', $project) }}" class="block overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow">
                        @if ($project->cover_image)
                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($project->cover_image) }}" alt="{{ $project->name }}" class="h-24 w-full object-cover">
                        @else
                            <div class="h-24 w-full bg-gray-100" aria-hidden="true"></div>
                        @endif
                        <div class="p-2">
                            <div class="text-sm font-semibold text-gray-800 truncate">{{ $project->name }}</div>
                            <div class="mt-0.5 text-xs text-gray-500 line-clamp-2">
                                @if ($project->description)
                                    <x-rich-text-excerpt :html="$project->description" :limit="60" />
                                @else
                                    &mdash;
                                @endif
                            </div>
                        </div>
                    </a>

                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <x-card class="text-center text-gray-500">
                        {{ __('You have no projects yet.') }}
                    </x-card>
                @endforelse
            </div>
    </div>
</x-app-layout>

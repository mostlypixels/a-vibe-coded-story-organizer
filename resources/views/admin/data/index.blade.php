<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuration') }}
        </h2>
    </x-slot>

    {{--
        Export & import shell (task 03). The backup/restore engine is a separate
        future spec (Q3): both tabs render a short "coming soon" line only — no
        forms, no routes that post anywhere.

        Tabs are inline Alpine (Q5: no reusable x-tabs component until a second
        screen needs one). The tabs follow the WAI-ARIA tabs pattern:
        role="tablist"/tab/tabpanel, aria-selected on the active tab, aria-controls
        wiring tab -> panel, a roving tabindex (only the active tab is in the tab
        order), and Left/Right arrow keys move between tabs. `activeTab` is the
        single source of truth for which tab/panel shows.
    --}}
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" x-data="{ activeTab: 'export' }">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Export & import') }}
        </h2>

        <div class="mt-6 border-b border-gray-200">
            <div
                role="tablist"
                aria-label="{{ __('Export and import') }}"
                class="-mb-px flex gap-2"
            >
                <button
                    id="tab-export"
                    type="button"
                    role="tab"
                    x-ref="tabExport"
                    aria-controls="panel-export"
                    :aria-selected="activeTab === 'export' ? 'true' : 'false'"
                    :tabindex="activeTab === 'export' ? 0 : -1"
                    @click="activeTab = 'export'"
                    @keydown.right.prevent="activeTab = 'import'; $refs.tabImport.focus()"
                    @keydown.left.prevent="activeTab = 'import'; $refs.tabImport.focus()"
                    :class="activeTab === 'export'
                        ? 'border-flame-500 text-navy-900'
                        : 'border-transparent text-gray-500 hover:text-navy-900 hover:border-gray-300'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150"
                >
                    {{ __('Export') }}
                </button>

                <button
                    id="tab-import"
                    type="button"
                    role="tab"
                    x-ref="tabImport"
                    aria-controls="panel-import"
                    :aria-selected="activeTab === 'import' ? 'true' : 'false'"
                    :tabindex="activeTab === 'import' ? 0 : -1"
                    @click="activeTab = 'import'"
                    @keydown.right.prevent="activeTab = 'export'; $refs.tabExport.focus()"
                    @keydown.left.prevent="activeTab = 'export'; $refs.tabExport.focus()"
                    :class="activeTab === 'import'
                        ? 'border-flame-500 text-navy-900'
                        : 'border-transparent text-gray-500 hover:text-navy-900 hover:border-gray-300'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150"
                >
                    {{ __('Import') }}
                </button>
            </div>
        </div>

        <div
            id="panel-export"
            role="tabpanel"
            aria-labelledby="tab-export"
            tabindex="0"
            x-show="activeTab === 'export'"
            class="mt-6 focus:outline-none"
        >
            <p class="text-sm text-gray-600">
                {{ __('Download one of your projects as a .zip archive: a human-readable reading version plus a lossless data copy.') }}
            </p>

            @if ($projects->isEmpty())
                {{-- Empty state: nothing to export until the user owns a project. --}}
                <p class="mt-4 text-sm text-gray-600">
                    {{ __('Create a project first to export it.') }}
                    <a href="{{ route('projects.create') }}" class="text-ocean-600 underline hover:text-ocean-800">
                        {{ __('Create a project') }}
                    </a>
                </p>
            @else
                <form method="POST" action="{{ route('admin.data.export') }}" class="mt-6 space-y-6 max-w-lg">
                    @csrf

                    <div>
                        <x-input-label for="project_id" :value="__('Project')" />
                        <select
                            id="project_id"
                            name="project_id"
                            class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                        >
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
                    </div>

                    <div>
                        <label for="include_images" class="inline-flex items-center">
                            <input
                                id="include_images"
                                type="checkbox"
                                name="include_images"
                                value="1"
                                @checked(old('include_images', true))
                                class="rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                            >
                            <span class="ms-2 text-sm text-gray-700">{{ __('Include images & files') }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('include_images')" class="mt-2" />
                    </div>

                    <x-primary-button>{{ __('Export') }}</x-primary-button>
                </form>

                {{--
                    Epub export (task 07). A second, fully independent form/picker from the
                    zip export above (grilled decision: two forms, no shared Alpine picker —
                    simplicity over deduplicating one <select>). Posts to a different route;
                    its project selector uses a distinct id (`epub_project_id`) so the page
                    keeps unique element ids while both forms reuse the `project_id` name.
                    The empty-project error is surfaced here because task 06 keys the
                    EpubExportException redirect to the `project_id` error bag.
                --}}
                <section class="mt-10 border-t border-gray-200 pt-8" aria-labelledby="epub-export-heading">
                    <h3 id="epub-export-heading" class="text-base font-medium text-gray-900">
                        {{ __('Epub export') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Download one of your projects as an EPUB e-book, ready to open in any e-reader.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.data.export.epub') }}" class="mt-6 space-y-6 max-w-lg">
                        @csrf

                        <div>
                            <x-input-label for="epub_project_id" :value="__('Project')" />
                            <select
                                id="epub_project_id"
                                name="project_id"
                                class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                            >
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
                        </div>

                        <x-primary-button>{{ __('Download EPUB') }}</x-primary-button>
                    </form>

                    <p class="mt-4 text-xs text-gray-500">
                        {{ __('For full EPUB conformance verification, validate the downloaded file with the official') }}
                        <a href="https://www.w3.org/publishing/epubcheck/" class="text-ocean-600 underline hover:text-ocean-800 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm" target="_blank" rel="noopener">
                            {{ __('epubcheck') }}
                        </a>
                        {{ __('tool.') }}
                    </p>
                </section>
            @endif
        </div>

        {{-- The inactive panel starts hidden via an inline style so there is no
             flash before Alpine boots; x-show then manages its visibility. --}}
        <div
            id="panel-import"
            role="tabpanel"
            aria-labelledby="tab-import"
            tabindex="0"
            x-show="activeTab === 'import'"
            style="display: none;"
            class="mt-6 focus:outline-none"
        >
            <p class="text-sm text-gray-600">
                {{ __('Importing a backup will be available soon.') }}
            </p>
        </div>
    </div>
</x-admin-layout>

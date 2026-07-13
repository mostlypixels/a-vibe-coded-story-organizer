<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    {{--
        Flash feedback for the import flows that redirect back here (queued,
        discarded, mid-run failure) and for the "Import settings" save. Synchronous
        success redirects to the new project instead, so it is not shown here.
        'import-settings-updated' is a marker (mirroring the crawler-settings form);
        any other status is a ready-to-display human message from ImportController.
    --}}
    @if (session('status'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-navy-900"
        >
            {{ session('status') === 'import-settings-updated' ? __('Import settings saved.') : session('status') }}
        </div>
    @endif

    {{--
        Import settings (task 08): the global ImportSetting singleton — archive
        size cap (edited in human-friendly MB, persisted as KB by the Form Request)
        and the background-processing toggle. Its own small card directly above the
        Export/Import tabbed card, following resources/views/admin/settings/edit's
        form conventions. Any authenticated user may edit it (like CrawlerSetting).
    --}}
    <x-card class="max-w-md">
        <x-slot name="header">
            <x-heading level="4">{{ __('Import settings') }}</x-heading>
        </x-slot>

        <form method="POST" action="{{ route('admin.data.import-settings') }}" class="space-y-4">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="max_archive_megabytes" :value="__('Maximum archive size (MB)')" />
                <input
                    id="max_archive_megabytes"
                    type="number"
                    name="max_archive_megabytes"
                    min="1"
                    value="{{ old('max_archive_megabytes', intdiv($importSetting->max_archive_kilobytes, 1024)) }}"
                    class="mt-1 block w-32 border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                >
                <x-input-error :messages="$errors->get('max_archive_megabytes')" class="mt-2" />
            </div>

            <div class="flex items-start gap-3">
                <input
                    type="checkbox"
                    id="run_in_background"
                    name="run_in_background"
                    value="1"
                    @checked(old('run_in_background', $importSetting->run_in_background))
                    class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                >
                <div>
                    <x-input-label for="run_in_background" :value="__('Process imports in the background')" />
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Requires a running queue worker (php artisan queue:work). Leave this off unless you\'ve set one up — imports will otherwise sit queued forever.') }}
                    </p>
                </div>
            </div>

            <x-button variant="primary">{{ __('Save') }}</x-button>
        </form>
    </x-card>

    {{--
        Export & import shell (task 03), now with a real Import tab (task 08). The
        Export tab posts to ExportController; the Import tab posts to
        ImportController (upload) and lists the user's in-progress imports with
        Resume/Discard actions.

        Tabs are inline Alpine (Q5: no reusable x-tabs component until a second
        screen needs one). The tabs follow the WAI-ARIA tabs pattern:
        role="tablist"/tab/tabpanel, aria-selected on the active tab, aria-controls
        wiring tab -> panel, a roving tabindex (only the active tab is in the tab
        order), and Left/Right arrow keys move between tabs. `activeTab` is the
        single source of truth for which tab/panel shows.
    --}}
    <x-card x-data="{ activeTab: 'export' }">
        <x-slot name="header">
            <x-heading level="3">{{ __('Export & import') }}</x-heading>
        </x-slot>

        <div class="border-b border-gray-200">
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

                    <x-button variant="primary">{{ __('Export') }}</x-button>
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
                    <x-heading level="4" id="epub-export-heading">{{ __('Epub export') }}</x-heading>
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

                        <x-button variant="primary">{{ __('Download EPUB') }}</x-button>
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
                {{ __('Upload a .zip previously exported from this app (or another instance of it) to create a new project from it.') }}
            </p>

            <form method="POST" action="{{ route('admin.data.import') }}" enctype="multipart/form-data" class="mt-6 space-y-6 max-w-lg">
                @csrf

                <div>
                    <x-input-label for="archive" :value="__('Archive (.zip)')" />
                    <input
                        id="archive"
                        type="file"
                        name="archive"
                        accept=".zip"
                        class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-ocean-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-ocean-700 hover:file:bg-ocean-100"
                    >
                    <p class="mt-1 text-xs text-gray-500">{{ __('Up to :size MB', ['size' => intdiv($importSetting->max_archive_kilobytes, 1024)]) }}</p>
                    <x-input-error :messages="$errors->get('archive')" class="mt-2" />
                </div>

                <x-button variant="primary">{{ __('Import') }}</x-button>
            </form>

            {{--
                In-progress / stalled imports (task 08). A crash in any phase after
                `project` leaves an actionable Import row; list them so a stalled
                import is never silently invisible. Resume re-runs from the last
                checkpoint (idempotent); Discard deletes the partial project and the
                row via the shared <x-delete-button> confirm form. The list is empty
                (and hidden) on the common path where nothing is mid-flight.
            --}}
            @if ($imports->isNotEmpty())
                <div class="mt-8 space-y-3" aria-labelledby="imports-heading">
                    <x-heading level="4" id="imports-heading">{{ __('In-progress imports') }}</x-heading>

                    @foreach ($imports as $import)
                        <div class="flex items-center justify-between rounded-md border border-gray-200 p-4">
                            <div>
                                <p class="text-sm font-medium text-navy-900">{{ $import->archiveOriginalName() }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ __('Status: :phase', ['phase' => $import->phase->label()]) }}
                                    @if ($import->failure_message)
                                        &mdash; {{ $import->failure_message }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.data.imports.resume', $import) }}">
                                    @csrf
                                    <x-button variant="secondary">{{ __('Resume') }}</x-button>
                                </form>
                                <x-delete-button
                                    :action="route('admin.data.imports.destroy', $import)"
                                    :confirm="__('Discard this import? Anything already created will be deleted.')"
                                >{{ __('Discard') }}</x-delete-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-card>
</x-admin-layout>

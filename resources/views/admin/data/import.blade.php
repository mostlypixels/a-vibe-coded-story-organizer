<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @include('admin.data.partials.subnav')

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
            class="rounded-md border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-navy-900 mb-6"
        >
            {{ session('status') === 'import-settings-updated' ? __('Import settings saved.') : session('status') }}
        </div>
    @endif

    {{--
        Import settings (task 08): the global ImportSetting singleton — archive
        size cap (edited in human-friendly MB, persisted as KB by the Form Request)
        and the background-processing toggle. Any authenticated user may edit it
        (like CrawlerSetting).
    --}}
    <x-card class="max-w-md mb-8">
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

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Import') }}</x-heading>
        </x-slot>

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
    </x-card>
</x-admin-layout>

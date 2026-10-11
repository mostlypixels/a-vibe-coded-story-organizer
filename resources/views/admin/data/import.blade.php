<x-admin-layout :title="__('Import')">
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @include('admin.data.partials.subnav')

    @if (session('status'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-info bg-info-surface px-4 py-3 text-sm text-info-surface-content mb-6"
        >
            {{ session('status') === 'import-settings-updated' ? __('Import settings saved.') : session('status') }}
        </div>
    @endif

    @if (session('warning'))
        <x-alert variant="warning" class="mb-6">{{ session('warning') }}</x-alert>
    @endif

    <x-card icon="tabler-settings" class="max-w-md mb-8">
        <x-slot name="header">
            <x-heading level="4" icon="tabler-settings">{{ __('Import settings') }}</x-heading>
        </x-slot>

        <form method="POST" action="{{ route('admin.data.import-settings') }}" class="space-y-4">
            @csrf
            @method('patch')

            <x-field
                name="max_archive_megabytes"
                :label="__('Maximum archive size (MB)')"
                :hint="$serverUploadMegabytes === null ? null : __('The server accepts uploads up to :size MB.', ['size' => $serverUploadMegabytes])"
            >
                <x-text-input
                    id="max_archive_megabytes"
                    type="number"
                    name="max_archive_megabytes"
                    min="1"
                    :max="$serverUploadMegabytes"
                    :value="old('max_archive_megabytes', intdiv($importSetting->max_archive_kilobytes, 1024))"
                    class="mt-1 block w-32"
                />
            </x-field>

            <p class="text-sm text-content-muted" data-run-mode="{{ $runInBackground ? 'background' : 'inline' }}">
                {{ $runInBackground ? __('Imports run in the background.') : __('Imports run during the upload.') }}
            </p>

            <x-button variant="primary">{{ __('Save') }}</x-button>
        </form>
    </x-card>

    <x-card icon="tabler-file-import">
        <x-slot name="header">
            <x-heading level="3" icon="tabler-file-import">{{ __('Import') }}</x-heading>
        </x-slot>

        <p class="text-sm text-content-muted">
            {{ __('Upload a .zip previously exported from this app (or another instance of it) to create a new project from it.') }}
        </p>

        <form method="POST" action="{{ route('admin.data.import') }}" enctype="multipart/form-data" class="mt-6 space-y-6 max-w-lg">
            @csrf

            <x-field name="archive" :label="__('Archive (.zip)')">
                <input
                    id="archive"
                    type="file"
                    name="archive"
                    accept=".zip"
                    class="mt-1 block w-full text-sm text-content-muted file:mr-4 file:rounded-md file:border-0 file:bg-info-surface file:px-4 file:py-2 file:text-sm file:font-medium file:text-info-surface-content hover:file:bg-info-surface/80"
                >
                <p class="mt-1 text-xs text-content-muted">{{ __('Up to :size MB', ['size' => intdiv($importSetting->max_archive_kilobytes, 1024)]) }}</p>
            </x-field>

            <x-button variant="primary">{{ __('Import') }}</x-button>
        </form>

        @if ($imports->isNotEmpty())
            <div class="mt-8 space-y-3" aria-labelledby="imports-heading">
                <x-heading level="4" id="imports-heading">{{ __('In-progress imports') }}</x-heading>

                @foreach ($imports as $row)
                    @php($import = $row['import'])
                    @php($polling = in_array($row['state'], ['queued', 'running'], true))
                    <div
                        class="flex items-center justify-between gap-4 rounded-md border border-border p-4"
                        data-import-state="{{ $row['state'] }}"
                        @if ($polling)
                            x-data="importStatus({{ Js::from(route('admin.data.imports.status', $import)) }}, {{ Js::from($row['state']) }}, {{ Js::from(__($import->phase->label())) }})"
                            x-bind:data-import-state="state"
                        @endif
                    >
                        <div>
                            <p class="text-sm font-medium text-content">{{ $import->archiveOriginalName() }}</p>
                            <p class="text-sm text-content-muted" aria-live="polite">
                                @if ($polling)
                                    <span x-show="state === 'queued'" @style(['display: none' => $row['state'] !== 'queued'])>{{ __('Waiting to start') }}</span>
                                    <span x-show="state === 'running'" class="inline-flex items-center gap-2" @style(['display: none' => $row['state'] !== 'running'])>
                                        <x-tabler-loader-2 class="h-4 w-4 animate-spin" aria-hidden="true" />
                                        <span x-text="label">{{ __($import->phase->label()) }}</span>
                                    </span>
                                    <span x-show="state === 'completed'" style="display: none">{{ __('Done') }}</span>
                                @elseif ($row['state'] === 'duplicate')
                                    {{ __('You imported this archive on :date as :project.', $row['duplicate']) }}
                                @else
                                    {{ __($import->phase->label()) }}
                                    @if ($import->failure_message)
                                        &mdash; {{ $import->failure_message }}
                                    @endif
                                @endif
                            </p>
                        </div>

                        <div class="flex gap-2">
                            @if ($polling)
                                <x-button variant="secondary" href="#" x-show="state === 'completed'" x-bind:href="projectUrl" style="display: none">{{ __('Open project') }}</x-button>
                                {{-- No Discard while a run holds the import: it would delete rows under a running phase. --}}
                                @if ($row['state'] === 'queued')
                                    <x-delete-button
                                        x-show="state === 'queued'"
                                        :action="route('admin.data.imports.destroy', $import)"
                                        :confirm="__('Discard this import? Anything already created will be deleted.')"
                                    >{{ __('Discard') }}</x-delete-button>
                                @endif
                            @else
                                <form method="POST" action="{{ route('admin.data.imports.resume', $import) }}">
                                    @csrf
                                    <x-button variant="secondary">{{ $row['state'] === 'duplicate' ? __('Import anyway') : __('Resume') }}</x-button>
                                </form>
                                <x-delete-button
                                    :action="route('admin.data.imports.destroy', $import)"
                                    :confirm="__('Discard this import? Anything already created will be deleted.')"
                                >{{ __('Discard') }}</x-delete-button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</x-admin-layout>

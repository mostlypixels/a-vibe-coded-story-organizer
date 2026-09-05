<x-admin-layout>
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

    <x-card class="max-w-md mb-8">
        <x-slot name="header">
            <x-heading level="4">{{ __('Import settings') }}</x-heading>
        </x-slot>

        <form method="POST" action="{{ route('admin.data.import-settings') }}" class="space-y-4">
            @csrf
            @method('patch')

            <x-field name="max_archive_megabytes" :label="__('Maximum archive size (MB)')">
                <x-text-input
                    id="max_archive_megabytes"
                    type="number"
                    name="max_archive_megabytes"
                    min="1"
                    :value="old('max_archive_megabytes', intdiv($importSetting->max_archive_kilobytes, 1024))"
                    class="mt-1 block w-32"
                />
            </x-field>

            <div class="flex items-start gap-3">
                <input
                    type="checkbox"
                    id="run_in_background"
                    name="run_in_background"
                    value="1"
                    @checked(old('run_in_background', $importSetting->run_in_background))
                    class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                >
                <div>
                    <x-input-label for="run_in_background" :value="__('Process imports in the background')" />
                    <p class="mt-1 text-sm text-content-muted">
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

                @foreach ($imports as $import)
                    <div class="flex items-center justify-between rounded-md border border-border p-4">
                        <div>
                            <p class="text-sm font-medium text-content">{{ $import->archiveOriginalName() }}</p>
                            <p class="text-sm text-content-muted">
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

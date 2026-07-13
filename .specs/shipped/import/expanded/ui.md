# Import — UI

## Import settings card (admin-configurable size cap)

Above the tab strip (or as its own small `<x-card>` directly above the existing
Export/Import tabbed card), a compact "Import settings" form editing the
`ImportSetting` singleton's `max_archive_kilobytes` (see `architecture.md`), same
form conventions as `resources/views/admin/settings/edit.blade.php`:

```blade
<x-card class="max-w-md mb-6">
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
```

The form works in megabytes for a human-friendly input; `UpdateImportSettingRequest`
converts to kilobytes before persisting to `max_archive_kilobytes` (mirroring how
`CodexMediaRules` stores kilobytes internally but presents human hints).
`DataTransferController::index()` passes `ImportSetting::current()` **and** the
signed-in user's `Import` rows (not yet `completed`, per the status list below) to
the view alongside `$projects`, same one-controller-many-view-concerns shape it
already has.

## In-progress / stalled imports list

Below the upload form, list the importing user's non-`completed` `Import` rows
(there's rarely more than one, but a crash could leave several over time) so a
stalled import is never silently invisible:

```blade
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
```

A `queued` import (dispatched to `ProjectImportJob`, not yet picked up or still
running) shows the same row with phase `pending`/whatever it last committed —
"Resume" on a genuinely still-running queued job is harmless (idempotent: it just
re-dispatches from the current checkpoint), so there's no need to distinguish
"still running" from "crashed" in the UI; the user decides whether to wait, nudge it
along with Resume, or give up with Discard.

## Where it lives

`resources/views/admin/data/index.blade.php`'s `#panel-import` tab panel (currently
just "Importing a backup will be available soon.") becomes a real upload form,
following the same layout conventions as the Export panel right above it in the same
file: an `<x-card>` section, an `<x-input-label>`/`<x-input-error>` pair per field, an
`<x-button variant="primary">`.

```blade
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
</div>
```

The hint reads the same `ImportSetting::current()` value the Form Request validates
against, so the UI copy never drifts from the server-side `max:` rule — same
never-drift goal `CodexMediaRules::imageHint()`/`fileHint()` serve for Codex uploads,
just sourced from the database instead of a constant since this value is
admin-editable.

## Feedback

* **Success (synchronous)**: redirect straight to the new project's overview
  (`route('projects.show', $project)`) with a flash `status` message ("Project
  imported."), the same pattern `EpubExportController`/other admin actions already
  use — no interstitial "import in progress" page.
* **Queued**: redirect back to `admin.data.index` with a flash `status` message
  ("Import queued.") — the new `Import` row then shows up in the in-progress list
  above until the job completes and the row disappears (its `phase` reaches
  `completed`).
* **Validation/structural failure** (always synchronous — `ProjectImporter::start()`
  never queues a validation failure, per `architecture.md`): redirect back to
  `admin.data.index` with `$errors->get('archive')` populated with a specific,
  actionable message (which arborescence rule failed, which file didn't validate) —
  never a raw exception page. This mirrors `EpubExportController`'s existing pattern
  of catching a domain exception (`EpubExportException`) and turning it into a
  redirect-with-error rather than a 500; import should define and catch an
  equivalent `ImportValidationException` and any thrown `ArchiveValidator` failure
  should carry a message safe to show the user directly (no leaking internal paths/
  stack traces).
* **Mid-run failure** (a phase throws after validation already passed — a crash, a
  disk-full media copy, etc.): the `Import` row's `failure_message` is set and it
  surfaces in the in-progress list rather than a redirect error, since by this point
  the user may not even be looking at the original request (especially if queued).
* **Resume/Discard**: both redirect back to `admin.data.index` with a flash
  `status`; Discard reuses the existing `<x-delete-button>` component (already used
  wherever the app has a destructive delete action), whose built-in `confirm()`
  dialog makes clear that partially-created data will be deleted — the one
  genuinely destructive action this feature exposes.

## Accessibility

* The file input keeps a proper `<x-input-label>` (`for="archive"`), same as every
  other form field on this page — no relying on the `accept` attribute or placeholder
  text alone.
* Errors surface through the existing `<x-input-error>` component so they're
  associated with the field the same way every other form on this page already does;
  no new error-display pattern.
* No drag-and-drop-only upload path — a plain `<input type="file">` keeps this fully
  keyboard-operable, consistent with `CLAUDE.md`'s "ensure keyboard accessibility."

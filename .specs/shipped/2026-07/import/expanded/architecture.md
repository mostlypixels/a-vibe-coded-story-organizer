# Import — architecture

## Route & controller

Mirror the existing export routes in `routes/web.php` (both under the `auth` +
`admin` group that already holds `/data`, `/data/export`, `/data/export/epub`,
`/database`):

```php
use App\Http\Controllers\ImportController;

Route::post('/data/import', [ImportController::class, 'store'])->name('data.import');
Route::post('/data/imports/{import}/resume', [ImportController::class, 'resume'])->name('data.imports.resume');
Route::delete('/data/imports/{import}', [ImportController::class, 'destroy'])->name('data.imports.destroy');
```

`ImportController` (new, `app/Http/Controllers/ImportController.php`) stays thin, per
`CLAUDE.md`'s "controllers stay thin" convention. Whether it runs inline or dispatches
depends on `ImportSetting::current()->run_in_background` (see below):

```php
public function store(ImportProjectRequest $request): RedirectResponse
{
    $import = $this->importer->start(
        $request->file('archive'),
        $request->user(),
    );

    if (ImportSetting::current()->run_in_background) {
        ProjectImportJob::dispatch($import);

        return redirect()->route('admin.data.index')
            ->with('status', __('Import queued.'));
    }

    $this->importer->run($import);

    return $import->project
        ? redirect()->route('projects.show', $import->project)->with('status', __('Project imported.'))
        : redirect()->route('admin.data.index')->with('status', __('Import failed — see the Import tab for details.'));
}

public function resume(Import $import): RedirectResponse
{
    $this->authorize('resume', $import);

    // Resumes inline or re-dispatches, mirroring the same run_in_background choice `store()` made.
    ImportSetting::current()->run_in_background
        ? ProjectImportJob::dispatch($import)
        : $this->importer->run($import);

    return redirect()->route('admin.data.index');
}

public function destroy(Import $import): RedirectResponse
{
    $this->authorize('discard', $import);

    $this->importer->discard($import);

    return redirect()->route('admin.data.index')->with('status', __('Import discarded.'));
}
```

Authorization: for `store()`, per `CLAUDE.md`'s documented exception pattern (the same
one `CrawlerSetting` uses), there is no existing `Project` to walk up to — the request
just needs `auth`, and `ImportProjectRequest::authorize()` is `$this->user() !== null`.
State this explicitly in code comments so it doesn't read as a missed check, exactly
as `CLAUDE.md` calls out for `CrawlerSetting`. For `resume()`/`destroy()`, an `Import`
row **does** have an owner by then (`$import->user_id`), so these two actions get a
real `ImportPolicy` (`resume`/`discard`, both `$user->id === $import->user_id`) rather
than reusing the any-authenticated-user exception — this is the one part of the
feature that behaves like every other owned resource in the app, not like
`CrawlerSetting`.

## Form Request — `ImportProjectRequest`

`app/Http/Requests/ImportProjectRequest.php`, built the same way `ExportRequest` and
the Codex requests are: field-level Laravel validation for the upload shape, deferring
*structural* zip validation (arborescence, per-file content checks) to the service
layer, since Laravel's rule engine can't express "this file inside this zip must be
valid Markdown."

```php
public function rules(): array
{
    return [
        'archive' => ['required', 'file', 'mimes:zip', 'max:'.ImportSetting::current()->max_archive_kilobytes],
    ];
}
```

New `app/Support/ImportRules.php` (mirroring `CodexMediaRules`'s role as the single
source of truth for constants that *aren't* admin-configurable):
`SUPPORTED_MANIFEST_VERSIONS`, and the allowed top-level/`data/`-relative path
patterns, so the version gate and arborescence allow-list aren't magic
strings/numbers scattered across the service and its tests. The archive size cap is
**not** one of these constants — see below, it's admin-configurable — but
`ImportRules` still holds its *default* (`DEFAULT_MAX_ARCHIVE_KILOBYTES = 204800`,
i.e. 200 MB) for the migration/seed path.

### `ImportSetting` — the configurable size cap

Following the exact `CrawlerSetting` precedent (`app/Models/CrawlerSetting.php`,
`config/crawlers.php`, `GeneralSettingsController`) rather than hard-coding the
archive size limit as a plain constant: a new singleton, because this is a genuinely
admin-tunable operational limit, not a fixed business rule.

* Migration `create_import_settings_table`: singleton table (no `project_id`/
  `user_id`, same rationale comment as `crawler_settings`), two columns:
  `max_archive_kilobytes` (integer, default `204800` — 200 MB — matching
  `config('import.default_max_archive_kilobytes')`) and `run_in_background`
  (boolean, default `false` — synchronous is the safe default for a non-technical,
  no-queue-worker install; matching `config('import.default_run_in_background')`),
  the same "config value seeds the lazy-create path, column default is a backstop"
  pattern `crawler_settings` documents.
* `app/Models/ImportSetting.php`: `fillable = ['max_archive_kilobytes', 'run_in_background']`,
  `current()` static accessor identical in shape to `CrawlerSetting::current()`
  (lazily creates from the two `config('import.*')` defaults on first read), **not**
  memoised for the same reason (a settings update followed by an import attempt in
  the same request/test).
* `config/import.php`: `'default_max_archive_kilobytes' => (int) env('IMPORT_MAX_ARCHIVE_KILOBYTES', 204800)`,
  `'default_run_in_background' => (bool) env('IMPORT_RUN_IN_BACKGROUND', false)`.
* **Where it's edited**: the "Export & import" page (`admin.data.index`,
  `DataTransferController`), not the general Configuration/crawler page — this
  setting is specific to import, and the Data page is already the home for
  export/import concerns, mirroring how `DatabaseConfigurationController` gets its
  own section rather than being folded into `GeneralSettingsController`. Add a small
  "Import settings" card above (or beside) the Import tab: the size field plus a
  "Process imports in the background" checkbox, with a short note that it requires a
  running queue worker (`php artisan queue:work`) — turning it on with no worker
  running means imports sit `queued` forever, which is why it defaults **off**. Both
  fields post to the same `ImportSettingController@update` (`PATCH
  admin.data.import-settings`), authorized the same any-authenticated-user way as
  every other admin section (`UpdateImportSettingRequest::authorize()` is
  `$this->user() !== null`).
* `ImportProjectRequest::rules()` reads the current cap from `ImportSetting::current()`
  at validation time (never a hard-coded `max:`), so lowering/raising it from the
  admin UI takes effect immediately with no deploy. `ImportController` reads
  `run_in_background` the same way at submit time (see *Route & controller* above).

## Service — `ProjectImporter`

Per `CLAUDE.md` ("create a Service the first time an action needs non-trivial,
reusable logic" — this is that first time for imports, following the same precedent
`StaticSiteExporter` set for exports): `app/Services/ProjectImporter.php`,
HTTP-agnostic (methods take an `UploadedFile`/`Import`, never a `Request`), so it
stays trivially reusable and testable, as `export_md_archive`'s `00-overview.md` did
for the exporter, and callable identically from `ImportController` (inline) or
`ProjectImportJob` (queued). Three entry points, matching the checkpointing design in
`data-model.md`:

* `start(UploadedFile $archive, User $user): Import` — runs `ArchiveValidator` +
  `ContentSanitizer` against the whole archive (nothing is ever queued or run
  further if this fails), stores the archive to `archive_path`, creates the
  `Import` row at `phase = pending`, and returns it. **Always synchronous**, even
  when `run_in_background` is on — validation failure must surface immediately as a
  form error (per `ui.md`), never as a queued-job failure the user has to go check for.
* `run(Import $import): void` — runs `ProjectGraphImporter` phase-by-phase starting
  right after `$import->phase`, updating `$import->phase`/`id_maps` as each phase's
  transaction commits. Called directly by `ImportController::store()`/`resume()`
  when synchronous, or from inside `ProjectImportJob::handle()` when queued —
  identical code path either way, only the caller differs.
* `discard(Import $import): void` — the cleanup path from `data-model.md`'s
  *Discard* section.

Composed of smaller collaborators rather than one large class:

* `ArchiveValidator` (`app/Services/Import/ArchiveValidator.php`) — the security
  gate, run **before any row is written**:
  1. Open with `ZipArchive::open()`; reject anything that isn't a valid zip
     (`ZipArchive::open()`'s return code, not just "the extension was `.zip`").
  2. Walk every entry name in the central directory: reject any entry whose
     normalized path escapes its expected prefix (`..`, absolute paths, or a
     resolved path outside `data/` — the classic zip-slip check) **before**
     extracting anything.
  3. Reject any entry outside the allow-listed arborescence
     (`ImportRules`'s path patterns: `data/manifest.json`, `data/project/**`,
     `data/acts/**`, `data/timeline/**`, `data/codex/**`, `data/tags.json`) — `book/`
     and `README.md` are allowed to be present (they're part of a real export) but are
     never read; anything else (a stray `.php`, `.htaccess`, symlink entry) fails
     validation.
  4. Parse `data/manifest.json`; reject if missing, malformed, or its `version` isn't
     in `ImportRules::SUPPORTED_MANIFEST_VERSIONS`.
  5. For every JSON descriptor: decode and validate against an expected shape (the
     required keys per `documentation/export-format.md`) — malformed JSON or a
     missing required key fails validation with the offending file path in the error.
  6. For every declared media file (`entry.json`'s `media[].file`): verify the actual
     file exists at that path inside the zip, its size matches the JSON's declared
     `size` within tolerance, and re-derive its type from **content**, not extension —
     `finfo_file()`/`getimagesize()` for image collections, rejecting anything whose
     real content doesn't match its declared `mime_type`/collection (e.g. a renamed
     `.php` masquerading as `.jpg`). This directly satisfies the source spec's "does
     each file validate as exactly what it is supposed to be?".
* `ContentSanitizer` (`app/Services/Import/ContentSanitizer.php`) — wraps the
  **existing** `App\Services\HtmlSanitizer`/`RichTextFields::ALLOWED_TAGS` (already
  trusted for `description`/`notes` elsewhere) as its allow-list, but applies an
  import-specific **policy** on top that differs deliberately from normal form
  submission: normal saves run content through `SanitizesRichHtml`'s mutator, which
  silently *strips* anything outside the allow-list and saves the cleaned result.
  Import instead **rejects the whole archive** the moment any `description.html`/
  `notes.html` fragment (or, for `contents.md`, its rendered-Markdown output — see
  below) contains a tag/attribute outside `RichTextFields::ALLOWED_TAGS` — it never
  silently persists a stripped, mutated version of what the archive claimed to
  contain, since a bulk untrusted upload deserves a hard failure with a clear error
  rather than a quietly-altered import. The allow-list itself is never duplicated —
  only the reject-vs-strip policy differs from the rest of the app. For `contents.md`
  (Scene prose), reuse `App\Rules\ValidMarkdown` for well-formedness, **plus** the
  same stricter check: render the Markdown and run the result through the same
  allow-list, rejecting the file if the rendered output contains any tag outside it
  (CommonMark's raw-HTML passthrough is otherwise a hole). This is what satisfies "do
  the markdown files contain only markdown and html."
* `ProjectGraphImporter` (`app/Services/Import/ProjectGraphImporter.php`) — the
  id-remapping/insertion logic from `data-model.md`, one method per `ImportPhase`
  (`importProject()`, `importTimeline()`, `importStory()`, `importCodex()`), **each
  wrapped in its own `DB::transaction()`** (per `CLAUDE.md`'s "use database
  transactions for multi-step write operations") rather than one transaction for the
  whole import — this is what makes a phase resumable: a failure partway through a
  phase rolls back only that phase, while prior committed phases and the `Import`
  row's checkpoint survive.

`ProjectImporter::start()` orchestrates: validate the whole archive → extract to a
directory scoped to the `Import` row (`storage/app/imports/<import-id>/`, kept until
completion or discard — **not** cleaned up in a `finally` like the exporter's temp
file, since it must survive a crash to support resume) → create the `Import` row.
`ProjectImporter::run()` then calls `ProjectGraphImporter`'s phase methods in order
starting after the current checkpoint, persisting `phase`/`id_maps` after each one
commits, until `phase = completed` (at which point the extracted directory and
`archive_path` are finally deleted).

## Queued mode — `ProjectImportJob`

`app/Jobs/ProjectImportJob.php` implements `ShouldQueue`; `handle()` is a one-line
delegation to `app(ProjectImporter::class)->run($this->import)` — all the actual
logic lives in the service, never in the job, so the exact same phase/resume
behavior applies whether an import runs inline or queued. If the job itself fails
(worker killed, exception thrown), the `Import` row is left at its last committed
phase exactly as a crashed synchronous run would be — the **same** resume/discard UI
handles both cases identically; the job doesn't need its own recovery logic.

## Naming collisions

Per `overview.md`'s goal, importing never blocks on an existing project with the same
name (there's no unique constraint to violate) — the new project is created with the
archive's `name` value, and if a project with that exact name already exists for the
user (case-insensitive match), the new project's `name` gets a timestamp suffix (see
`data-model.md` → *Project identity / naming*, `open-questions.md` question 1 —
resolved). This check lives in `ProjectGraphImporter::importProject()` (phase 1),
not the controller.

## UI

See `ui.md` for the Import tab of `resources/views/admin/data/index.blade.php`.

## Security summary (cross-reference to `overview.md`'s attacker user story)

| Concern (from the source spec)               | Where it's enforced                                          |
|------------------------------------------------|----------------------------------------------------------------|
| "is it a zip?"                                 | `ArchiveValidator` step 1                                       |
| "is the arborescence valid?"                   | `ArchiveValidator` steps 2–3 (zip-slip + allow-list)             |
| "are there files that are not supposed to be there?" | `ArchiveValidator` step 3                                  |
| "does each file validate as exactly what it is supposed to be?" | `ArchiveValidator` steps 5–6 (JSON shape, content-sniffed media) |
| "do the markdown files contain only markdown and html?" | `ContentSanitizer` (rendered-output allow-list check)     |
| "project created for the current user"         | `ImportController`/`ImportProjectRequest` (auth-only gate, `$request->user()` is the sole owner) |

# Task 06 — HTTP layer

## Scope

Wire `ProjectImporter` (task 05) up to real routes: upload, resume, discard, plus
the `ImportSetting` admin form. **Does not** add the queued-dispatch branch yet
(task 07) — for this task, `store()`/`resume()` always call `ProjectImporter::run()`
inline, as if `run_in_background` were permanently `false`; task 07 adds the
conditional. **Does not** build the Blade views (task 08) — use minimal/placeholder
views or none yet; this task's tests hit routes directly via `route()`, not by
clicking through a page.

* `app/Http/Requests/ImportProjectRequest.php`: `authorize()` →
  `$this->user() !== null`; `rules()` → `archive` required/file/`mimes:zip`/
  `max:`+`ImportSetting::current()->max_archive_kilobytes`.
* `app/Http/Requests/UpdateImportSettingRequest.php`: `authorize()` →
  `$this->user() !== null`; `rules()` → `max_archive_megabytes` required integer
  min 1, `run_in_background` sometimes boolean. Converts megabytes → kilobytes
  before the controller persists (either here via a `validated()` accessor, or in
  the controller — pick whichever this codebase's existing unit-conversion Form
  Requests do, if any; otherwise controller-side is fine).
* `app/Http/Controllers/ImportController.php`: `store()`, `resume()`, `destroy()` —
  exactly the shape in `architecture.md`'s *Route & controller* section, minus the
  `run_in_background` branch (always call `$this->importer->run($import)` inline for
  now).
* `app/Http/Controllers/ImportSettingController.php`: `update()` — thin, mirrors
  `GeneralSettingsController::update()`'s shape.
* Routes in `routes/web.php`, inside the existing `admin` group:
  `POST /data/import` (`admin.data.import`), `POST /data/imports/{import}/resume`
  (`admin.data.imports.resume`), `DELETE /data/imports/{import}`
  (`admin.data.imports.destroy`), `PATCH /data/import-settings`
  (`admin.data.import-settings`).
* `DataTransferController::index()`: pass `ImportSetting::current()` and the
  signed-in user's non-`completed` `Import` rows to the view (even though task 08
  builds the view that reads them).

## Depends on

Task 05 (`ProjectImporter`), Task 01 (`ImportPolicy`, `ImportSetting`).

## Key decisions already made

* `store()`'s authorization is the any-authenticated-user exception;
  `resume()`/`destroy()` use the real `ImportPolicy` — don't unify these.
* `ImportProjectRequest`'s `max:` rule reads `ImportSetting::current()` live, never
  a hard-coded number.
* Failure feedback: a validation failure (`ImportValidationException`) redirects
  back with `$errors->get('archive')` populated; a mid-run failure (thrown from
  `run()`) is caught by the controller, leaves `failure_message` on the `Import` row
  (already set by task 05), and redirects to `admin.data.index` with a generic
  flash rather than a field error (there's no `archive` field to attach it to once
  past validation).

## Docs to consult

`architecture.md` → *Route & controller*, *Form Request*, *`ImportSetting`* sections
(the PHP snippets there are close to literal — implement against them, then adjust
only where the actual codebase's conventions differ).

## Tests

`tests/Feature/ImportTest.php` (or `ImportProjectTest.php`):

* Happy path: `actingAs($user)->post(route('admin.data.import'), ['archive' => ...])`
  with a real exported zip creates a `Project` owned by `$user` and redirects to it.
* Guest hitting any of the four routes is redirected/denied, matching this app's
  existing admin-route guest behavior.
* `ImportProjectRequest` validation failures: no file, non-zip file, oversized file
  (`assertSessionHasErrors('archive')`).
* An archive failing `ArchiveValidator`/`ContentSanitizer` (a fixture with a
  zip-slip entry, or disallowed HTML) redirects with a specific `archive` error, not
  a 500.
* `resume()`/`destroy()`: owner succeeds; a different authenticated user gets 403
  (the negative case `CLAUDE.md` requires for every authorization check).
* `PATCH admin.data.import-settings`: owner-authenticated user can update both
  fields; assert the persisted `max_archive_kilobytes` reflects the megabytes input
  converted correctly, and a subsequent import attempt is validated against the new
  cap (not a stale value).

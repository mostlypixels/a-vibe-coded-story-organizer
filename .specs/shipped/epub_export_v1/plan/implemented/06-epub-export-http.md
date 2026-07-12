# 06 — Epub export HTTP layer

## Scope

- New route in `routes/web.php`, sibling to the existing export route:
  `Route::post('/data/export/epub', [EpubExportController::class, 'store'])->name('data.export.epub');`
- New `app/Http/Controllers/EpubExportController.php` — thin, mirrors
  `app/Http/Controllers/ExportController.php`: resolve `Project` by `project_id`, authorize
  `view`, call `EpubExporter::export($project)`, stream the result back as a download with
  `Content-Type: application/epub+zip` and `deleteFileAfterSend(true)`. Catch
  `EpubExportException` (task 05) and `redirect()->back()->withErrors([...])` instead of
  letting it bubble as a 500.
- New `app/Http/Requests/EpubExportRequest.php` — mirrors `ExportRequest`: `authorize()` walks
  `ProjectPolicy@view` via `project_id`, `rules()` validates `project_id` exists. No
  `include_images`-equivalent option.

## Explicitly not in scope

- The export page's "Epub export" section/form itself (task 07) — this task only needs the
  route to exist and be postable, tested directly via the HTTP layer without a UI.

## Depends on

05 (needs a working, validated `EpubExporter::export()` and `EpubExportException` to call
and catch).

## Key decisions already made

- Synchronous download, no queue — identical shape to `ExportController::store()`.
- Authorization is `ProjectPolicy@view`, mirrored in both the controller and the Form
  Request — a foreign `project_id` must 403, never silently export another user's project.
- `EpubExportException` → redirect-back-with-error (not a validation-layer duplicate check —
  see the second grill's resolution on this in `00-overview.md`).

## Docs to consult

- `expanded/architecture.md` — the exact controller/request code shape.
- `app/Http/Controllers/ExportController.php` + `app/Http/Requests/ExportRequest.php` — the
  precedent to mirror line-for-line where reasonable.

## Tests

New `tests/Feature/EpubExportTest.php` (mirrors whatever `ExportTest`-equivalent covers the
existing zip export — locate it first and match its style):
- Happy path: owner posts to `admin.data.export.epub`
  (or whatever the route's actual name ends up being once nested under the admin route group —
  confirm during implementation) with a project that has content; response is a successful
  file download with the right content type.
- Authorization: non-owner posting a foreign `project_id` gets a 403.
- Guest (unauthenticated): redirected to login, matching the `/admin` gate.
- Validation — missing `project_id` or a non-existent one: standard Laravel validation error.
- Empty-content project: request redirects back with a session error instead of downloading a
  file (exercises the `EpubExportException` → redirect path end-to-end over HTTP, not just at
  the service-unit level from task 05).

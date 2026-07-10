# Task 01 — Export endpoint, form, and exporter skeleton

## Scope

Build the complete, verifiable end-to-end slice: a user submits the Export form and gets a
real `.zip` download containing (at minimum) `data/manifest.json`. Later tasks fill the zip
with the Story/Timeline/Codex `data/` branches and the `book/` layer.

**This task builds:**

- Add `"ext-zip": "*"` to `composer.json` `require`.
- **Route**: `POST /admin/data/export` → `ExportController@store`, named `admin.data.export`,
  inside the existing `admin` group (`auth` + `can:access-admin`) in `routes/web.php`.
- **`app/Http/Controllers/ExportController.php`** — thin `store(ExportRequest, StaticSiteExporter)`:
  resolve project → `authorize('view', $project)` → call exporter → stream download with
  `->deleteFileAfterSend(true)`. Download name:
  `Str::slug($project->name) . '-' . now()->format('Ymd-His') . '.zip'`,
  `Content-Type: application/zip`.
- **`app/Http/Requests/ExportRequest.php`**:
  - `authorize()`: load `Project::find(project_id)`; return
    `$project !== null && $this->user()->can('view', $project)` → foreign/missing id = 403.
  - `rules()`: `project_id` required|integer|`Rule::exists('projects','id')`;
    `include_images` `sometimes|boolean`. Read it with `$request->boolean('include_images')`
    (absent checkbox → false).
- **`app/Services/StaticSiteExporter.php`** — the HTTP-agnostic engine (see invariant 5 in
  `00-overview.md`). Public entry:
  `public function export(Project $project, bool $includeMedia): string` returning the temp
  zip path. For this task it:
  - opens a `ZipArchive` on a fresh temp path (e.g. `storage_path('app/exports/'.Str::uuid().'.zip')`,
    ensure the dir exists),
  - writes `data/manifest.json` = `{ version: 1, project_id, exported_at: <ISO 8601>,
    includes_media: <bool> }`,
  - closes and returns the path.
  - Clean up the temp file on exception (try/finally around the build; the controller owns
    post-send deletion). Provide small private helpers now so later tasks reuse them:
    `slug(string $name): string` (`Str::slug`, fallback `'untitled'` when empty) and
    `addFromString(ZipArchive $zip, string $path, string $contents)`.
- **Export form** in `resources/views/admin/data/index.blade.php`, `#panel-export` (keep the
  existing WAI-ARIA tab shell — only replace the panel body):
  - `<form method="POST" action="{{ route('admin.data.export') }}">` + `@csrf`.
  - Native `<select name="project_id">` styled like `x-text-input`, listing the current
    user's projects (`$projects`), with `x-input-label` + `x-input-error`.
  - Checkbox `name="include_images"` `value="1"` `checked`, label **"Include images & files"**.
  - `x-primary-button` "Export".
  - **No-projects empty state**: when `$projects` is empty, render a short
    "Create a project first to export it." line (link to project creation) instead of the form.
- **Provide `$projects`** to the view from the controller that renders it
  (`DataTransferController@index`): the signed-in user's projects ordered by name. Use the
  same access pattern the Dashboard uses (add `User::projects()` `hasMany` if the app doesn't
  already expose it; otherwise mirror the existing `where('user_id', …)` query — do **not**
  invent a second pattern).
- **`documentation/export-format.md`**: create it and document the `data/manifest.json`
  shape + the `version` contract (this file is the growing spec for the future import).
- **`CHANGELOG.md`**: add an `Added` stub under `## [Unreleased]` (finalized in task 05).

**This task does NOT build** (later tasks own these):

- Any `data/` content beyond `manifest.json` → tasks 02 (Story), 03 (Timeline), 04 (Codex).
- The `book/` layer → task 05.
- The media-bytes copying (the `includeMedia` flag is threaded through now but only recorded
  in the manifest; actual media files → task 04).
- Any queued/async job (the service is *shaped* for it; the job is a future feature).

## Depends on

Nothing (first task).

## Key decisions already made (binding)

- Sync in-request build, temp file, `deleteFileAfterSend` (invariant 6).
- Auth = admin gate **plus** `authorize('view', $project)`; foreign/missing id → 403
  (invariant 1).
- Exporter is HTTP-agnostic, reads bytes off the disk, async-ready (invariant 5).
- Zip name includes the `Ymd-His` datetime.
- Toggle label is "Include images & files".

## Docs to consult

- `plan/00-overview.md` (defaults + invariants).
- `expanded/architecture.md` for the where-logic-lives mapping (controller/Form Request/
  Service split) and the admin-area authorization exception — **but** ignore its single-tree
  layout and `storyline.html`; this plan supersedes those.
- `resources/views/admin/data/index.blade.php` (the tab shell to preserve),
  `resources/views/components/text-input.blade.php` (select styling), `routes/web.php`
  (admin group).

## Tests (`tests/Feature/ExportTest.php`)

- **Happy path**: owner posts a valid `project_id` → 200, `Content-Type: application/zip`,
  `Content-Disposition` filename matches `<project-slug>-<digits>.zip`.
- **Manifest**: unzip; `data/manifest.json` exists, is valid JSON, `version === 1`,
  `project_id` matches, `includes_media` reflects the toggle (true and false cases).
- **Authorization**: user A posting user B's `project_id` → 403; unauthenticated POST →
  redirect to login.
- **Validation**: missing `project_id` → `assertSessionHasErrors('project_id')`; non-boolean
  `include_images` → `assertSessionHasErrors('include_images')`.
- **Form render**: `GET admin.data.index` shows the Export form with the user's projects in
  the select; a user with no projects sees the empty-state copy, not the form.

# Export to static files — Architecture

## Where the logic lives (guidelines mapping)

| Concern                                   | Home                                                                 |
|-------------------------------------------|----------------------------------------------------------------------|
| HTTP entry (show form, handle export)     | `ExportController` (admin group) — thin: authorize → delegate → return |
| Input validation                          | `ExportRequest` (Form Request)                                       |
| The export workflow (walk, render, zip)   | `App\Services\StaticSiteExporter` (Service — a real multi-step reusable workflow) |
| Filename slug/number rules                | small private helpers in the service, or `App\Support\ExportPaths` if reused |
| HTML/MD rendering of each entity          | Blade templates under `resources/views/exports/` rendered to string  |
| Image → entity/collection mapping         | manifest built by the service from `codex_media`                     |

This follows CLAUDE.md → *Where logic lives*: the controller stays thin, the non-trivial
multi-step workflow becomes a **Service** (the first genuine `app/Services` fit for export —
mirrors the existing `CodexMediaService`, `AttributeTimeline`), and presentation stays in
**Blade** (`view(...)->render()`) rather than string-building HTML in PHP.

## Routes

Add inside the existing `admin` group (`routes/web.php`, `can:access-admin` + `auth`):

```php
Route::get('/data', [DataTransferController::class, 'index'])->name('data.index'); // existing
Route::post('/data/export', [ExportController::class, 'store'])->name('data.export'); // NEW
```

- Keep the existing `data.index` GET (the tabbed shell). Either move its `index()` onto the
  new `ExportController` or leave `DataTransferController::index` as-is and add
  `ExportController::store` — **recommend** leaving `DataTransferController` as the section
  shell and adding a focused `ExportController` with a single `store` action (SRP; import is
  a future controller). Confirm in Q7.
- `POST` (not `GET`): the export is a non-idempotent, potentially expensive action that
  produces a download; a form `POST` also carries CSRF and the `include_images` toggle
  cleanly.

## Authorization — the admin-area exception, done right

The admin area authorizes via the **`access-admin` gate** (any authenticated user), NOT via
`ProjectPolicy` — see the route comments and CLAUDE.md → *Hidden from crawlers* exception.
But the export **reads a specific project's data**, and projects are user-owned. So the
export action must do **both**:

1. Pass the `access-admin` gate (route middleware — already there).
2. `$this->authorize('view', $project)` in `ExportController::store`, and mirror it in
   `ExportRequest::authorize()` (`$this->user()->can('view', $project)`).

> [!WARNING]
> Without step 2 a signed-in user could export **another user's** project by POSTing a
> foreign `project_id`. The `access-admin` gate is "any authenticated user"; it is **not**
> ownership. This is the one place an admin-area action legitimately walks `ProjectPolicy`.
> Cover the 403 in tests.

## `ExportRequest` (Form Request)

```php
public function authorize(): bool
{
    $project = Project::find($this->input('project_id'));
    return $project !== null && $this->user()->can('view', $project);
}

public function rules(): array
{
    return [
        'project_id'     => ['required', 'integer', Rule::exists('projects', 'id')],
        'include_images' => ['sometimes', 'boolean'],
    ];
}
```

- `include_images` from an unchecked checkbox is absent → treat absent as `false`
  (`$request->boolean('include_images')`).
- Validate `project_id` exists; ownership is enforced in `authorize()` (403, not a validation
  error) so a foreign id is a 403 not a 422 — matches the project's authorization convention.

## `StaticSiteExporter` service — responsibilities

A single public entry point returning a path to a ready temp zip (the controller streams it):

```php
public function export(Project $project, bool $includeImages): string; // returns temp zip path
```

Internally, decomposed into readable private steps:

1. **Load** the project tree + codex media (see `data-model.md` → Read model).
2. **Open a `ZipArchive`** on a fresh temp file (`tempnam()` / `storage_path('app/exports/…')`).
3. **Storyline**: render `exports.storyline` → add as root `storyline.html`.
4. **Walk acts → chapters → scenes**, building directory paths `NN-slug/…`:
   - act dir `index.html` ← `exports.act`
   - chapter dir `index.html` ← `exports.chapter`
   - scene `NN-slug.html` ← `exports.scene`
   - scene `NN-slug.md` ← `exports.scene-markdown` (frontmatter + raw `contents`)
5. **Images** (only if `$includeImages`): copy each `codex_media` file into
   `images/…` and accumulate manifest rows; write `images/manifest.json`.
6. **Close** the zip; return its path.

Helpers:

- `slug($name)` → `Str::slug($name)`, with a fallback (`'untitled'`) when the name slugs to
  empty (Q4).
- `numberedName($position, $name)` → `sprintf('%02d-%s', $position, slug($name))`.
- `renderView($view, $data)` → `view($view, $data)->render()` (keeps HTML in Blade).

> [!NOTE]
> Wrap the file cleanup so the temp zip is deleted after the response is sent
> (`->deleteFileAfterSend(true)` on the download response), and delete it too if an
> exception is thrown mid-build. Do the actual work outside a DB transaction — it is
> read-only; no transaction needed (CLAUDE.md transactions rule is for multi-step *writes*).

## Controller flow

```php
public function store(ExportRequest $request, StaticSiteExporter $exporter): BinaryFileResponse
{
    $project = Project::findOrFail($request->integer('project_id'));
    $this->authorize('view', $project);

    $zipPath = $exporter->export($project, $request->boolean('include_images'));

    return response()
        ->download($zipPath, Str::slug($project->name).'.zip', ['Content-Type' => 'application/zip'])
        ->deleteFileAfterSend(true);
}
```

## Export artifact layout

For a project with two acts (only the shape shown):

```
<project-slug>.zip
└── (zip root)
    ├── storyline.html                     # compiled manuscript (all scene prose, in order)
    ├── 01-the-beginning/                   # act: NN-slug, NN = act.position
    │   ├── index.html                      # act name + rich-HTML description
    │   ├── 01-arrival/                      # chapter: NN-slug, NN = chapter.position
    │   │   ├── index.html                   # chapter name + description
    │   │   ├── 01-the-door.html             # scene: all fields as HTML
    │   │   ├── 01-the-door.md               # scene: frontmatter + raw Markdown contents
    │   │   ├── 02-inside.html
    │   │   └── 02-inside.md
    │   └── 02-the-cellar/
    │       └── …
    ├── 02-rising-action/
    │   └── …
    └── images/                             # ONLY when "include images" is on
        ├── manifest.json
        └── <NN-entry-slug>/<collection>/<original-name>
```

- **"except for the story itself"**: the Story section's children (acts) sit at the **zip
  root**, not under a `story/` folder. The root *is* "the story folder that contains the
  acts", so `storyline.html` lives there (spec: *"The compiled storyline … should be in the
  story folder"*).
- Codex / Timeline menu sections: **scope decision** — see Q1. The layout above ships the
  Story tree + storyline + images/manifest as the core. If Q1 says include Codex/Timeline
  HTML pages, add sibling `codex/` and `timeline/` folders at the root.

## Images & manifest

When `include_images` is on, for each `CodexMedia` row:

- Copy the stored file (original bytes, original name) to
  `images/<NN-entry-slug>/<collection>/<original_name>` — human-browsable, grouped by
  entity then field.
- Append a manifest row. `images/manifest.json` is an array of:

```json
{
  "file": "images/01-alice/cover/portrait.jpg",
  "entity_type": "codex_entry",
  "codex_entry_id": 42,
  "codex_entry_type": "character",
  "codex_entry_name": "Alice",
  "collection": "cover",
  "original_name": "portrait.jpg",
  "mime_type": "image/jpeg"
}
```

This satisfies both readings of the spec's *"connect the images to the entity … and the
field name"*: the **folder path** connects them for a human, the **manifest** connects them
for a machine. See Q2/Q3 for the format decision.

## Blade export templates (`resources/views/exports/`)

New, minimal templates rendered to string (not part of the app layout — standalone HTML):

- `exports/layout.blade.php` — a bare `<!doctype html>` wrapper (`<meta charset>`, minimal
  inline CSS or none; Q6) that the others `@extends`.
- `exports/storyline.blade.php` — loops acts → chapters → scenes, emits headings + each
  scene's `{!! Str::markdown($scene->contents) !!}`.
- `exports/act.blade.php` / `exports/chapter.blade.php` — name + `x-rich-text`/verbatim
  description.
- `exports/scene.blade.php` — all scene fields (see Q8 for field order/labels).
- `exports/scene-markdown.blade.php` — frontmatter block + raw `{{ $scene->contents }}`
  (NOT `Str::markdown`), for the `.md` file (Q9 defines frontmatter keys).

Reuse `Str::markdown()` for prose (the existing render path in `story/index` and
`shared/scenes/show`) and render rich-HTML fields verbatim ("HTML exported as is").

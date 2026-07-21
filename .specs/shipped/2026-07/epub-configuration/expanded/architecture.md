# Epub Configuration — Architecture

## A. Splitting the Export & import page

### Routes (`routes/web.php`, inside the existing `admin.` group)

Replace the single `GET /admin/data` page with three server-rendered views. The **POST**
actions (`admin.data.export`, `admin.data.export.epub`, `admin.data.import`,
`admin.data.imports.*`, `admin.data.import-settings`) keep their names and controllers so
nothing downstream breaks.

```php
// Landing → first sub-page.
Route::get('/data', fn () => redirect()->route('admin.data.export-project'))->name('data.index');

Route::get('/data/export/project', [DataTransferController::class, 'exportProject'])
    ->name('data.export-project');
Route::get('/data/export/ebook',   [DataTransferController::class, 'exportEbook'])
    ->name('data.export-ebook');
Route::get('/data/import',         [DataTransferController::class, 'import'])
    ->name('data.import.index');

// NEW: persist the epub configuration (separate from the export POST).
Route::patch('/data/export/ebook/{project}/settings',
    [EpubSettingController::class, 'update'])->name('data.epub-settings.update');
```

- Keep `admin.data.*` as the route-name namespace so `sidebar.blade.php`'s
  `request()->routeIs('admin.data.*')` keeps the "Export & import" entry active across all three
  pages — **no sidebar change needed**.
- The `.index` redirect preserves any existing `route('admin.data.index')` links.

### Controllers — keep them thin (CLAUDE.md "where logic lives")

**`DataTransferController`** becomes three GET actions, each returning its own view with only
the data that page needs (drop the current all-in-one payload):

- `exportProject()` → `$request->user()->projects()->orderBy('name')->get()` → the `.zip` form
  view.
- `exportEbook()` → the same project list **plus**, for the selected/first project, its
  `epubSettingOrDefault()` → the config form view. (Project selection interaction: Q4.)
- `import()` → `ImportSetting::current()` + the user's non-completed imports → the import view.

**POST controllers unchanged:** `ExportController@store`, `EpubExportController@store`,
`ImportController@*`, `ImportSettingController@update`.

**New `EpubSettingController@update(UpdateEpubSettingRequest, Project $project)`** — resolve
(route-model-bound), `authorize('update', $project)`, `firstOrNew` the setting, fill validated
input, save, redirect back with a status flash. Mirrors `ImportSettingController`'s shape.

### Sub-navigation

A shared partial `resources/views/admin/data/partials/subnav.blade.php` renders three **links**
(not Alpine tabs) to the routes above, each with `aria-current="page"` when
`request()->routeIs(...)` — the same active-state convention as `sidebar.blade.php`. All three
page views (`export-project`, `export-ebook`, `import`) `@include` it under the section heading.
This directly satisfies the spec's "no longer javascript tabs, but separate controller actions".

## B. Export honouring the configuration

### Where the choice enters the service

`EpubExporter::export(Project $project)` reads `$project->epubSettingOrDefault()` **once** at the
top and threads that `EpubSetting $settings` object through the private render/build methods.
Keep the service HTTP-agnostic (as it is today) — it takes the `Project`, not the request. Do
**not** pass 20 booleans around; pass the `EpubSetting`.

> [!WARNING]
> **Do not fork the render path.** `EpubExporter` must still render scene bodies through its own
> private SmartPunct `CommonMarkConverter` — never `Scene::renderedContents`. The new Markdown
> fields (dedication/preface/…) render through that **same** converter. Keep HTML in Blade views
> under `resources/views/exports/epub/`, never string-built in PHP (the existing, grilled rule).

### Method-by-method impact on `EpubExporter`

- **`applyMetadata()`** — wrap each optional block in its toggle: `include_author && filled(...)`,
  same for publisher, rights, isbn. Title, language, primary URN identifier, and accessibility
  metadata stay unconditional.
- **`applyCover()`** — gate on `$settings->include_project_cover`.
- **`addFrontMatter()`** — after the title page and **before** the TOC page, emit the enabled,
  non-empty front-matter pages in a fixed reading order (recommended: **dedication → preface**;
  acknowledgements placement is Q5). Each is a new Blade view (below) rendered to XHTML, run
  through `assertXmlWellFormed()`, and `addChapter()`-ed at nav root level. Postface and the
  appendix are emitted **after** all Act/Chapter content, at the end of `addNavigation()` (or a
  new `addBackMatter()`).
- **`renderChapter()`** — heading now comes from `ChapterTitleFormat::format($position, $name)`;
  optionally prepend `Chapter.description`; interleave scenes with the chosen `DividerType`
  instead of the hard-coded `<hr/>`; optionally render `Scene.name` as a per-scene heading and
  `Scene.description` above each body. All of this moves into the `chapter.blade.php` view driven
  by flags passed in the view data — keep the branching in Blade, not the service.
- **`renderAct()`** — optionally render `Act.description` (today explicitly omitted).
- **`chapterNavTitle()` / `actNavTitle()`** — chapter labels now come from `ChapterTitleFormat`
  (single source of truth shared with the heading). Act label unchanged.
- **`renderToc()` / `addNavigation()`** — honour `TableOfContentsDepth`:
  - `Acts`: only Act entries in the in-book TOC and nav (no chapter children).
  - `Chapters`: today's two-level tree (default).
  - `Scenes`: three levels — needs **stable per-scene anchors**. Give each scene an `id` in
    `chapter.blade.php` (e.g. `id="scene-{$scene->id}"`) and link the TOC/nav to
    `chapter-{id}.xhtml#scene-{id}`. Scene nav labels use `Scene.name`, falling back to
    `"Scene {position}"` when unnamed.
- **New `addBackMatter()`** — postface page, then the codex appendix (below).

### The codex appendix

Heaviest addition; consider landing it as the **last** implementation task so the rest can ship
independently (Q7).

- Load the project's `codexEntries()` filtered to `appendix_entry_types`, ordered by
  (`type`, `name`). Eager-load `media` when `appendix_include_images` is on.
- Render an appendix section: an appendix cover/heading page, then one entry per page (or grouped
  by type) via a new `resources/views/exports/epub/appendix-entry.blade.php`.
- **Content conversion caveat:** `CodexEntry.description` is **rich HTML** (sanitized on write),
  *not* Markdown — unlike scenes. The appendix view embeds that stored HTML directly; it must
  still be **XML-well-formed** to pass `validatePackage()`. Rich HTML from HTMLPurifier is not
  guaranteed XHTML (void elements, unclosed tags). **Recommendation:** run each entry's HTML
  through a normalise-to-XHTML step (`DOMDocument::loadHTML` → `saveXML` on the body fragment)
  before embedding, and cover it with a well-formedness test. This is a real risk — flag in Q7.
- **Images:** embed each entry's first media image via `$book->addImageFile(...)`, reading bytes
  off `Storage::disk('public')` exactly like `applyCover()` (never the `/storage` URL). Skip
  missing files silently, as the cover already does.

### New enums drive rendering, not conditionals-in-service

`ChapterTitleFormat::format()`, `TableOfContentsDepth`, and `DividerType` own their own
behaviour (labels, formatting, the divider HTML snippet). The service and views ask the enum;
they don't `match` on raw strings. This keeps the config's "single source of truth" per
CLAUDE.md (no magic strings) and makes adding the V2 `image` divider a one-case change.

## C. Authorization

- **`EpubSettingController@update`** and **`UpdateEpubSettingRequest::authorize()`** both
  `->can('update', $project)` — the write path is ownership, mirrored, per CLAUDE.md. A non-owner
  `project_id`/route binding is **403**.
- The **export** path keeps `authorize('view', $project)` (read), already mirrored in
  `EpubExportRequest`.
- The GET config page: `exportEbook()` only ever lists the **current user's** projects, so the
  selector can't reference a foreign project; still `authorize('view', $project)` before loading a
  specific project's settings.

## D. Form Requests & Rules

- **`UpdateEpubSettingRequest`** — `authorize()` as above; `rules()`:
  - every `include_*` → `['boolean']`;
  - `chapter_title_format` / `table_of_contents_depth` / `divider_type` → `[Rule::enum(...)]`;
  - `appendix_entry_types` → `['array']`, `appendix_entry_types.*` →
    `[Rule::enum(CodexEntryType::class)]`;
  - the four Markdown fields → `['nullable', 'string', new ValidMarkdown]` (reuse the existing
    rule). Consider a shared `max` length consistent with other text fields.
  - **Where do the Markdown fields save?** They live on `projects` (data-model Q1), so this
    request can update **both** the `Project` columns and the `EpubSetting` row in one transaction,
    or split into the project-fields form on the project edit page (Q4). Recommendation: edit them
    on the Export-ebook page in one save, wrapped in a `DB::transaction`.
- Reuse `Rule::enum(...)` for every enum (CLAUDE.md: "Validate enums with `Rule::enum(...)`").

## E. Files touched / added (summary)

| Kind        | Path                                                              | Action |
| ----------- | ---------------------------------------------------------------- | ------ |
| Migration   | `..._add_frontmatter_to_projects_table.php`                      | add    |
| Migration   | `..._create_epub_settings_table.php`                             | add    |
| Model       | `app/Models/EpubSetting.php`                                     | add    |
| Model       | `app/Models/Project.php` (relation, accessor, fillable)          | edit   |
| Enum        | `app/Enums/ChapterTitleFormat.php`, `TableOfContentsDepth.php`, `DividerType.php` | add |
| Controller  | `DataTransferController` (3 GET actions)                         | edit   |
| Controller  | `app/Http/Controllers/EpubSettingController.php`                 | add    |
| Request     | `app/Http/Requests/UpdateEpubSettingRequest.php`                 | add    |
| Service     | `app/Services/EpubExporter.php`                                  | edit   |
| Service     | `StaticSiteExporter` + `ProjectImporter` (+ `ImportRules`, manifest version) | edit |
| Views       | `admin/data/{export-project,export-ebook,import}.blade.php` + `partials/subnav` | add/split |
| Views       | `exports/epub/{dedication,preface,postface,acknowledgements,appendix-entry}.blade.php` | add |
| Views       | `exports/epub/{chapter,act,toc}.blade.php`, `styles.css`         | edit   |
| Routes      | `routes/web.php`                                                 | edit   |
| Policy      | `ProjectPolicy` already has `update`/`view` — **no change**      | —      |

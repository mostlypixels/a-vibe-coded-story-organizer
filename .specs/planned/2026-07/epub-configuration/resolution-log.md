# Epub Configuration — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and issues → resolutions
found while implementing and verifying this feature. The `plan-implementer` agent appends here per
task; `ship-plan` consolidates it. Read it before extending the feature.

## Feedback & decisions

- **Markdown fields on `projects`**, edited on the **project edit page** (plain textarea +
  `ValidMarkdown`), reusable by a future "share full story" feature; ebook config page shows only
  the include-toggles + help text pointing there. Kept Markdown (not rich HTML) — the epub is a
  clean typographic document, matching `Scene.contents`.
- **`PublicationSetting`** (not `EpubSetting`), lazy `publicationSettingOrDefault()`, no backfill.
- **Project + chapter covers in v1** via a shared **`CoverImageService`** (second caller justifies
  the extraction). Image dividers + per-scene images stay V2.
- **Section order is fully sortable** (ordered JSON array; `title` pinned, `toc`/`body` movable;
  move-up/down idiom); include-toggles gate on/off independently.
- **Config travels in the archive** (one manifest bump); malformed config on import ⇒ default +
  continue, never reject the content. Forged media still rejects the archive.
- **Rich HTML → XHTML** via one shared `RichText::toXhtmlFragment()` (descriptions + appendix).
- **Appendix**: v1, final task, first image per entry.
- **Ebook form**: reload-on-select, separate Save + Download buttons; Download exports the *saved*
  config.
- **Per-task model assignment** (decided with the user): Opus for 05/07/09/10/13 (security,
  investigative, or heaviest surface), Haiku for 01/06 (mechanical, test-guarded), Sonnet for the
  rest. Fable deliberately unused (no coding edge here; quota risk). See `plan/00-overview.md`.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

### Task 01 (enums-and-publication-setting)

- **No issues.** Implementation complete. All tests pass (614 tests, 2301 assertions). Three enums created with `label()` methods and behaviour (`ChapterTitleFormat::format()`, `DividerType::dividerHtml()`). `PublicationSetting` model with enum/json casts. `Project::publicationSettingOrDefault()` returns unsaved defaults with all attributes initialized.

- **Open (task 10):** confirm `rampmaster/phpepub` supports a **third** nav level + fragment hrefs
  for `table_of_contents_depth = scenes`. If not, degrade to chapter-level nav + in-page
  `#scene-{id}` anchors and record the outcome here.

### Task 03 (split-export-import-pages)

- **No behaviour-changing issues.** Split `admin/data/index.blade.php` (Alpine
  `role="tablist"` tabs) into three server-rendered pages: `export-project.blade.php`,
  `export-ebook.blade.php`, `import.blade.php`, each `@include`-ing a new
  `admin/data/partials/subnav.blade.php` (ordinary `<a>` links, `aria-current="page"`
  driven by `routeIs(...)`, the same idiom as `admin/partials/sidebar.blade.php`). `GET
  /admin/data` now only redirects to `admin.data.export-project`; the name
  `admin.data.index` is kept (only as a redirect target) since the sidebar's "Export &
  import" link and several POST-redirect call sites reference it by name.
- **Redirect targets for POST controllers reassigned per the plan's "flash block moves
  with its owning page" rule:** `ImportController` and `ImportSettingController` now
  `redirect()->route('admin.data.import.index')` instead of `admin.data.index` (the
  Import-settings card + status flash now live only on the Import page).
  `EpubExportController`'s error path already used `redirect()->back()`, so it
  automatically lands on whichever page posted — no controller change needed there,
  only its tests' `from()`/`assertRedirect()` targets moved to `admin.data.export-ebook`.
  `ExportController` never redirects (download-or-nothing), so it needed no change.
- **`DataTransferController`** split into three thin actions (`exportProject`,
  `exportEbook`, `import`) per the architecture doc; each returns only the view data its
  own page needs (the old single `index()` action and its all-in-one payload are gone).
- **`export-ebook.blade.php` hosts only the existing epub download form for now** — no
  config form yet (explicitly deferred to task 04); its structure otherwise mirrors
  `export-project.blade.php`.
- **`AdminConfigurationTest` required updates beyond ExportTest/ImportTest/ImportSettingTest**
  (not explicitly named in the task, but broken by the split since it exercised
  `admin.data.index` as a real 200 page): `sectionRouteNames()` and the
  any-authenticated-user/sidebar-active-state tests now target
  `admin.data.export-project` (a concrete page) instead of `admin.data.index` (now a
  bare redirect, so `assertOk()` on it would fail with a 302). The two tests asserting
  on the removed `role="tablist"`/`#tab-export`/`#panel-import` markup
  (`test_export_import_page_renders_both_tabs`,
  `test_export_import_page_shows_export_form_and_import_upload_form`) were deleted —
  their coverage is superseded by the new `DataTransferTest`.
- **Issue found in verification, not caught by the test suite as first written:**
  `test_each_section_marks_only_its_own_sidebar_link_active` asserted exactly one
  `aria-current="page"` occurs anywhere in the page — this broke once the sub-nav (also
  using `aria-current="page"`, per the plan's explicit instruction to reuse that idiom)
  coexists with the sidebar on the same page. Root cause: the original assertion counted
  `aria-current` page-wide instead of scoping to the sidebar's own `<nav
  aria-label="Configuration">` element. Fixed by extracting that `<nav>...</nav>` block
  (same regex-scoping pattern the file already used for `<main>`) before counting.
  `composer test` was green before this fix in the sense that it ran — it caught the
  regression correctly; flagging here because it's the kind of interaction (two
  independent features both legitimately using `aria-current="page"` on one page) a
  future task should watch for again in task 04's config form page.
- **New `tests/Feature/DataTransferTest.php`** added per the task's "Tests to add":
  each of the three pages `assertOk` and shows only its own controls, the
  `data.index` → `export-project` redirect, sub-nav `aria-current` scoped per page, the
  sidebar entry staying active on all three, and a guard that the old tab markup
  (`role="tablist"`, `#tab-export`, `#tab-import`) is gone.
- Verified the runtime surface: `npm run build` succeeded (no `public/hot` stale
  file), then drove the app in a real browser via the `run-imagoldfish` skill —
  logged in as the seeded dev user, navigated to `/admin/data` (redirected to
  `/admin/data/export/project`), `/admin/data/export/ebook`, and `/admin/data/import`;
  screenshotted all three. Each shows its own heading/controls, the sub-nav
  highlights the current page (flame underline), and the sidebar's "Export & import"
  entry stays highlighted active across all three — no console errors.

### Task 02 (project-frontmatter-fields)

- **No issues.** Migration `add_frontmatter_to_projects_table` adds `dedication`,
  `acknowledgements`, `preface`, `postface` (nullable `text`, after `cover_image`) to `projects`;
  added to `Project::$fillable`. Project edit page gets a new "Book front & back matter
  (Markdown)" card with four plain `<textarea>`s (not `x-wysiwyg`) + `<x-input-error>`, using
  `old(...)`. `UpdateProjectRequest` validates each with `['nullable', 'string', 'max:20000', new
  ValidMarkdown]`. `StoreProjectRequest` was left untouched — the create form has no book-metadata
  section at all (language/author/etc. aren't there either), so there was no existing rule set to
  extend.
- **Manifest version bump (owned by this task, overview #7):** `StaticSiteExporter::DATA_VERSION`
  bumped from 1 to 2; `ImportRules::SUPPORTED_MANIFEST_VERSIONS` extended to `[1, 2]`. Documented
  the bump as a deliberate exception to `documentation/export-format.md`'s normal "additive
  changes don't bump" rule, since 00-overview.md explicitly wants ONE bump covering every new
  field the whole feature adds (this task's four columns, chapter covers in task 07, the
  serialized `PublicationSetting` in task 05) rather than one per task. `ExportTest`'s manifest
  assertion updated from `1` to `2` accordingly.
- **Field-file pattern reused verbatim:** the four Markdown fields are written as
  `dedication.md`/`acknowledgements.md`/`preface.md`/`postface.md` siblings under `data/project/`,
  linked from `project.json` via `*_file` keys — exactly the existing `addFieldFile()` convention
  scene `contents.md` uses. No `ImportRules`/`ArchiveValidator` allow-list change was needed:
  `data/project/` was already an allowed directory prefix, and `project.json`'s required-keys list
  (`id`, `name`) doesn't need the new optional keys added.
- **Import side** reuses `ProjectGraphImporter::readMarkdownField()` (already existed for
  `contents_file`) with the new `*_file` key names — it already returns `null` when the key is
  absent, so a pre-bump (version 1) archive imports the new columns as `null` with no code change
  beyond wiring the four calls into `importProject()`. Verified via `ImportTest`'s existing
  version-1 fixture archive (`makeValidUpload()`), which has no `dedication_file` etc. keys.
- Verified the edit-page render via `Blade::render`-equivalent (`view('projects.edit', ...)
  ->render()` in tinker against the real dev DB after `php artisan migrate`): all four textareas
  render with their `name` attributes and posted values. No JS/asset changes in this task, so no
  `npm run build` was needed.

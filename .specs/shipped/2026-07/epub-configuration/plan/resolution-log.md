# Epub Configuration — Resolution Log

Per-feature record of deviations, issues → resolutions, and feedback/decisions that a green
test suite alone would not surface. Keep entries short and root-cause-first.

## Task 04 — Publication-setting config form & persistence

### Deviations

- **`section_order` was missing from the task-01 deliverable.** Task 01's own file specified a
  `section_order` json column, but neither the migration (`create_publication_settings_table`)
  nor the `PublicationSetting` model actually included it — task 04 is the first real consumer
  and would have been unimplementable without it. Filled the gap with a *new* migration
  (`2026_07_17_000001_add_section_order_to_publication_settings_table`) rather than editing the
  already-implemented task 01 migration, added `section_order` to `PublicationSetting`'s
  `$fillable`/`$casts` plus the two new constants `SECTION_KEYS` / `PINNED_FIRST_SECTION`, and
  added the matching default to `Project::publicationSettingOrDefault()` and
  `PublicationSettingFactory`. No other task-01 deliverable was touched.
- **Section reordering is three separate routes/actions, not folded into the main PATCH.** The
  task text allowed either "submit an ordered array, or per-item move actions mirroring
  `ActController::moveUp`" — chosen the latter (`PublicationSettingController::moveSectionUp` /
  `moveSectionDown`, using the existing `x-icon-move-up-button` / `x-icon-move-down-button`
  components exactly like Acts/Chapters/Scenes) because it reuses those components verbatim and
  avoids a hidden-field/JS reconciliation hack to merge "reorder" and "full settings save" into
  one form submission. The main config form still round-trips `section_order` via hidden inputs
  (so `UpdatePublicationSettingRequest::rules()`'s `section_order` + `ValidSectionOrder` rule is
  exercised on every save, as the task specified), but a reorder click hits its own dedicated
  route and only touches that one field.
- **Front/back-matter Markdown text is NOT edited on this page.** `ui.md`'s "Export-ebook config
  form" section (written before the later refinement) shows textareas for dedication/preface/etc.
  Per the **binding** overview decision #1 and this task's own "Does not" line, only the
  `include_*` checkboxes live here; the Markdown text stays exclusively on the project edit page
  (task 02). Each checkbox shows "(text set)"/"(empty)" next to it so the toggle's effect is
  visible without leaving the page.

### Issues → resolutions

- **Dev `database/database.sqlite` lacked the new `section_order` column during manual
  verification**, even though `composer test` was green (in-memory DB migrates fresh every run).
  `php artisan migrate` was required before the page could be exercised in a real browser.
  Recorded here as a reminder for the next task that touches this table.

### Feedback/decisions

- None beyond what's captured above — no user course-correction mid-task.

## Verification (task 04)

- `composer test`: 641 tests, 2413 assertions, all green (includes the new
  `tests/Feature/PublicationSettingTest.php`, 22 tests).
- `composer lint`: Pint — passed, no changes needed.
- `npm run build`: succeeded; `public/hot` absent, so the app serves the built assets.
- Manual browser verification (`run-imagoldfish` skill, Playwright): logged in as the seeded
  `admin@example.com`, loaded `/admin/data/export/ebook?project=25` for a project named "Verify
  Me" with `author` and `dedication` set. Confirmed: every fieldset (Content options, Front &
  back matter, Metadata, Formatting, Appendix) renders with the project's real stored values
  ("Include author: *Jane Doe*", "Include dedication (text set)"); the Section order list shows
  all 8 keys; clicking "Include codex appendix" reveals the entry-type checkboxes and "Include
  images" via Alpine `x-show` with no console errors; clicking a section's "Move down" button
  redirected back with the "Ebook configuration saved." flash and durably swapped
  `dedication`/`acknowledgements` in the persisted `section_order` (confirmed via `tinker`).

## Task 05 — PublicationSetting travels in the archive

### Deviations

- **Descriptor placement + allow-list entry.** The serialized config lives at a **flat**
  `data/publication-setting.json` (sibling of `data/manifest.json` / `data/tags.json`), not
  under `data/project/`. That location is not covered by any `ALLOWED_DIRECTORIES` prefix, so
  it was added to `ImportRules::ALLOWED_FILES` — the "extend the allow-list" the task named.
  `ArchiveValidator` therefore allow-lists the path but never schema-checks its content (it is
  in none of the required-keys maps); content validation is entirely the importer's job, which
  is exactly the untrusted-input posture the task wanted.
- **"Default" on import means the lazy default (no row), not a persisted default row.** A valid
  config creates a `publication_settings` row; an **absent OR malformed** config creates **no
  row**, so the imported project rides `Project::publicationSettingOrDefault()`. This matches
  the lazy pattern tasks 01/04 established and keeps the round-trip symmetric (a project with no
  setting exports no descriptor and imports no row).
- **`appendix_entry_types` "resolve against the archive's own codex types" = valid
  `CodexEntryType` values.** `CodexEntryType` is a fixed global enum, so unknown types are
  simply the ones that fail `CodexEntryType::tryFrom()`. They are filtered out **before**
  validation so an unknown type is dropped (not a hard failure that would discard the whole
  config), per the task's "drop unknowns rather than failing".

### Issues → resolutions

- **`FormRequest` already declares a non-static `validationRules()` method**, so the first
  attempt to expose the shared rules as `UpdatePublicationSettingRequest::validationRules()`
  was a **fatal error** ("Cannot make non static method … static"). A green PHPUnit run does
  not catch this — it is a class-load-time fatal that only fires when the importer path runs.
  Renamed the shared static method to **`configRules()`**; `rules()` delegates to it, and the
  importer's `Validator::make(...)` reuses it, so the config form and the import path validate
  against one identical rule set (no loose duplication).

### Feedback/decisions

- None beyond the above — no user course-correction mid-task.

## Verification (task 05)

- `composer test`: **647 tests, 2464 assertions**, all green (was 641 before; +6 in the new
  `tests/Feature/PublicationSettingArchiveTest.php` — customised round-trip equal, no-setting →
  default, three malformed-config fallbacks, and unknown-appendix-type drop).
- `composer lint`: Pint — passed (auto-fixed import ordering / fully-qualified types in the new
  test on the first pass, clean thereafter).
- **No runtime UI surface**: task 05 is data-layer only ("Does not render anything"). The full
  export → HTTP-import round-trip is exercised end-to-end by the feature test through the real
  `StaticSiteExporter` and the real `admin.data.import` route (security gate + sanitizer + graph
  importer all run), so there is no Blade/JS/build output to observe in a browser.

## Task 06 — Shared CoverImageService

### Deviations

- None — the task spec was followed exactly. Pure refactor, no behaviour change.

### Issues → resolutions

- **`EpubExporter` test helper instantiation.** The existing `EpubExporterTest::exporter()`
  method instantiated `EpubExporter` directly without arguments (`new EpubExporter`), but the
  service now requires a `CoverImageService` constructor dependency. Updated the test helper to
  resolve the service from the container: `new EpubExporter(app(CoverImageService::class))`.

### Feedback/decisions

- None — no user course-correction mid-task.

## Verification (task 06)

- `composer test`: **657 tests, 2476 assertions**, all green (was 647 before; +10 new tests in
  `tests/Unit/Services/CoverImageServiceTest.php` covering store returns path, delete is null-safe,
  bytes reads file contents, and mimeType reads mime type).
- `composer lint`: Pint — passed (auto-fixed spacing in the new service and test method naming
  on first pass, clean thereafter).
- **No behaviour change — existing tests are the guard**: the five cover-related tests in
  `ProjectTest` (upload, replace, remove, delete-cascade) all pass unchanged, as do all
  `EpubExporterTest` cover-embedding tests. The refactoring is complete and isolated.

## Task 07 — Chapter cover infrastructure

### Deviations

- **`StaticSiteExporter` reads chapter-cover bytes directly via `Storage::disk('public')`,
  not via `CoverImageService`.** The task/plan note asked to "reuse `CoverImageService`
  rather than duplicating disk logic", but the exporter already reads *codex* media bytes
  with a bare `Storage::disk('public')->get(...)` (its established, self-contained
  convention), and injecting a constructor dependency into `StaticSiteExporter` would have
  broken two tests that instantiate it with `new StaticSiteExporter` (no args:
  `ArchiveValidatorTest`, `ContentSanitizerTest`). Chapter covers therefore follow the
  *exporter's own* media convention there. `CoverImageService` **is** reused everywhere the
  intended consumers live: the controller (`store`/`delete`), the three cascade hooks
  (`delete`), and the importer (a new `storeImportedFile()` copy method mirroring
  `CodexMediaService::storeImportedFile()`).
- **Chapter covers live in the archive under the existing `data/acts/` prefix** (at
  `data/acts/<act>/chapters/<chapter>/cover/<name>`), linked by a new `cover_file` key in
  `chapter.json` — analogous to a codex entry's `cover/…` media. Because that path is already
  inside an allow-listed `ALLOWED_DIRECTORIES` prefix, **no `ImportRules` allow-list change
  was needed** (the "extend `ImportRules`/`ArchiveValidator`" in the task reduces to the new
  `ArchiveValidator` content-sniff for chapter covers). Covers are gated by the same
  `includes_media` toggle as codex media: a metadata-only export records the `cover_file`
  link but ships no bytes, and imports back a **null** cover (identical to a metadata-only
  codex-media row).
- **Chapter cover has no declared mime in the archive** (unlike codex media, which carries a
  `mime_type` in `entry.json`). `chapters.cover_image` is a plain path column with no mime
  metadata, so `ArchiveValidator::validateChapterCoverContent()` validates on **content
  alone**: `finfo` and `getimagesize` must agree on an allowed `IMAGE_MIME_TYPES` value. A
  forged image (renamed `.php`) fails both sniffers and rejects the archive.

### Issues → resolutions

- **Cascade-delete bypasses model events (the documented codex-media pitfall) at TWO levels
  for chapter covers.** A single-chapter delete fires `Chapter::deleting` (added), but
  deleting an **act** cascades to its chapters via the DB FK — skipping `Chapter::deleting` —
  and deleting a **project** cascades act→chapter, skipping both. Resolved with three hooks:
  `Chapter::deleting` (single delete), `Act::deleting` (purges its chapters' covers), and
  `Project::deleting` extended to purge every chapter cover project-wide (one query joined
  through the project's acts). All three reuse `CoverImageService::delete` (null-safe /
  idempotent, so overlapping paths never double-fault). Green tests would *not* have caught a
  missing hook without the disk assertions — `ChapterTest` now asserts the file is gone off
  the fake `public` disk after each of the three delete paths.
- **`ProjectGraphImporter` gained a constructor dependency (`CoverImageService`)**, which
  broke two spots that build it by hand: `ProjectImporterTest::realImporter()` (`new
  ProjectGraphImporter(...)`) and its Mockery partial mock (constructor-args array). Both
  updated to pass `app(CoverImageService::class)`. This is the same class-load-time trap task
  06 hit with `EpubExporter` — a green PHPUnit run surfaces it only because those specific
  tests exercise the constructor.
- **Story-phase file cleanup on rollback.** Copied cover bytes live outside the phase's
  `DB::transaction()`, so `importStory()` now tracks copied cover paths and unlinks them on
  any exception before rethrowing (mirroring `importCodex()`'s `$copiedPaths` pattern) — a
  rolled-back story phase never leaks orphan cover files.

### Feedback/decisions

- None beyond the above — no user course-correction mid-task.

## Verification (task 07)

- `composer test`: **670 tests, 2509 assertions**, all green (was 657 before; +13: 7 new
  `ChapterTest` cover tests — upload, replace, remove, invalid-file validation, and the three
  cascade-delete disk assertions; 4 new `ArchiveValidatorTest` chapter-cover tests — forged
  image rejected, genuine image accepted, metadata-only bytes-absent accepted, path-traversal
  rejected; 2 new `ImportRoundTripTest` tests — cover survives a with-media round-trip to a
  fresh path with matching bytes, and a metadata-only export imports a null cover). Non-owner
  ⇒ 403 is already covered by the existing `test_a_user_cannot_update_another_users_chapter`,
  which routes through the same `UpdateChapterRequest::authorize()`.
- `composer lint`: Pint — `passed` (both auto-fix and `--test` check-only).
- **Runtime surface (Blade) verified**: `npm run build` succeeded; `public/hot` absent (the
  app serves the built assets). Rendered `chapters.edit` for a chapter with a cover through
  the view layer and confirmed the served HTML contains `enctype="multipart/form-data"`, the
  `cover_image` file input, the `remove_cover_image` checkbox, the stored cover preview path,
  the "up to 5 MB" size hint, and `/build/assets/…` asset URLs (not a dev-server origin).
- **Dev DB**: ran `php artisan migrate` so the new `chapters.cover_image` column exists for
  manual browser exercise (the in-memory test DB migrates fresh each run).

## Task 08 — Exporter metadata/cover toggles + defaults===v1 guard

### Deviations

- **"Byte-for-byte defaults===v1" is implemented as an equivalence check, not a diff
  against a stored pre-feature snapshot.** There is no binary fixture of the exporter's
  output from before this task, and pinning one would itself be a source of flakiness
  (the underlying `rampmaster/phpepub` library stamps `dc:date`/`dcterms:modified` from
  `time()`, so a literal file checked into the repo would need to be regenerated on every
  guard run anyway). The regression test instead exports the same richly-populated
  project twice — once with **no** `PublicationSetting` row (the lazy default from
  `Project::publicationSettingOrDefault()`) and once with an **explicit** row created via
  `PublicationSetting::factory()` (whose `definition()` already mirrored
  `publicationSettingOrDefault()` field-for-field, confirmed by reading both) — and
  asserts the two `.epub` files are byte-identical via `assertSame` on the raw file
  contents. Because `applyMetadata()`/`applyCover()` are the only methods this task
  touches, and every toggle they read now defaults `true`, a byte match here proves the
  new gating is a true no-op for a default project. Content-level assertions (chapter
  heading format, `<hr/>` dividers, metadata presence, cover manifest entry) run against
  the same export as a secondary sanity check. This is the guard every later exporter
  task (09-13) must keep green as they add more gated toggles.
- No other deviation — `EpubExporter::export()` now resolves
  `$project->publicationSettingOrDefault()` once and threads the `PublicationSetting`
  object into `applyMetadata()`/`applyCover()` exactly as the task specified (no 20-bool
  parameter list).

### Issues → resolutions

- None — no traps hit. The existing `CoverImageService` constructor-injection pattern
  from task 06 was already in place, so adding a second dependency-shaped parameter
  (`PublicationSetting`, a plain value passed per-call rather than injected) required no
  test-helper rewiring beyond the new tests themselves.

### Feedback/decisions

- None — no user course-correction mid-task.

## Verification (task 08)

- `composer test`: **676 tests, 2542 assertions**, all green (was 670 before; +6 new
  tests in `tests/Unit/Services/EpubExporterTest.php` — the defaults===v1 byte-identical
  regression, `include_author=false`, `include_publisher=false`, `include_rights=false`,
  `include_isbn=false`, and `include_project_cover=false`).
- `composer lint`: Pint — passed, no changes needed.
- **No runtime UI surface**: task 08 only threads an existing lazy-loaded model into an
  existing private-service method signature; no Blade/route/JS changed. Verified via the
  service-level test suite only, per the task's own "Tests to add" section (unzip +
  assert on OPF entries, with `validatePackage()` running inside every `export()` call as
  the schema gate).

## Task 09 — Exporter content options (titles, descriptions, dividers)

### Deviations

- **`renderAct()` / `renderChapter()` / `renderToc()` gained a nullable `?PublicationSetting
  $settings = null` parameter rather than a required one.** These three methods are `public`
  and are called with two arguments both by the exporter's own pipeline and directly by
  several existing `EpubExporterTest` cases (and the task-08 defaults guard's
  `renderChapter(...)` sanity check). A required parameter would have broken those call sites;
  a defaulted-null parameter that resolves to `$project->publicationSettingOrDefault()` keeps
  every existing caller working while the export pipeline always threads the already-resolved
  setting through. The private `addFrontMatter()`/`addNavigation()` take it as a **required**
  parameter (fully under the service's control).
- **`chapterNavTitle()` now takes a `ChapterTitleFormat` and delegates to
  `$format->format($position, $name)`** — the enum is the single source of truth shared with
  the chapter page heading, replacing both the hard-coded `"Chapter {n}: {name}"` in the nav
  label AND the identical literal that used to live in `chapter.blade.php`. Default format
  (`chapter_number_title`) reproduces the old string exactly, so nav/TOC labels are unchanged
  for a default project.
- **Chapter heading is omitted entirely when the formatted heading is empty** (only possible
  with the `title` format on a name-less chapter). Rather than emit a blank `<h1></h1>`, the
  Blade guards `@if ($heading !== '')`. This extends the task's "empty content + toggle on ⇒
  render nothing" rule to the heading itself; the layout `<title>` falls back to
  `"Chapter {position}"` so the XHTML document title is never empty.
- **No CHANGELOG entry added.** Following the precedent of tasks 04/06/07/08 (which added
  none), the running `[Unreleased]` list holds only the two milestone entries (tasks 02 and
  05); the exporter's content-option rendering is still being wired incrementally (front/back
  matter — task 11 — and the appendix — task 13 — are still pending), so a consolidated
  user-facing entry is better written when the feature ships than as partial per-task noise.

### Issues → resolutions

- **The task-08 defaults===v1 guard is inherently flaky and started failing under the parallel
  runner.** Root cause: the guard exported the same project twice and compared the two `.epub`
  files as **one raw byte stream**, but the `rampmaster/phpepub` library stamps
  `dc:date`/`dcterms:modified` from `time()` at finalize. The two back-to-back exports (with a
  `PublicationSetting::factory()->create()` insert between them) straddle a wall-clock-second
  boundary often enough that under `composer test`'s 8-way parallelism the timestamps drift by
  one second and the byte comparison fails — even though **every content entry is byte-identical**
  (verified by unzipping both and diffing entry-by-entry: only the OPF's two timestamp lines
  differed; `chapter-*.xhtml`, `act-*.xhtml`, `toc.xhtml`, `styles.css`, cover, nav, NCX all
  matched). Task 08 flagged the `time()` stamping in its own log but assumed both exports land in
  the same second; that assumption is the latent flake, **not** anything task 09 changed (my
  content changes leave the two default paths producing identical bytes, which is the whole point
  the guard checks). A green PHPUnit run in isolation (`--filter`) hid it because a single serial
  export pair almost always lands in one second. **Fix:** hardened the guard to compare the two
  packages **entry-by-entry**, byte-for-byte, normalising **only** the OPF's `<dc:date>` and
  `dcterms:modified` lines (the sole `time()`-derived values) via two new private test helpers
  (`assertContentIdenticalIgnoringOpfTimestamp()` / `stripOpfTimestamps()`). This preserves the
  guard's exact intent — lazy-default and explicit-default produce identical **content** — while
  making it immune to the export-clock race. Re-ran the full suite twice: green both times.

### Feedback/decisions

- None — no user course-correction mid-task.

## Verification (task 09)

- `composer test`: **684 tests, 2593 assertions**, all green (was 676 before; +8 new tests in
  `tests/Unit/Services/EpubExporterTest.php`: each `ChapterTitleFormat` drives both heading and
  nav label; name-less chapter has no dangling separator/blank heading; scene titles on/off/empty;
  act description on/off/empty; chapter+scene descriptions on/off; descriptions-on-but-empty
  render no element; decorative divider swaps `<hr/>` and stays well-formed; a deliberately
  non-XHTML description — unclosed `<p>`, bare void `<br>`, written straight to the column past the
  sanitizing mutator — still clears `validatePackage()` after `toXhtmlFragment()` repairs it). Ran
  the full suite **twice** to confirm the previously-flaky task-08 guard is now stable.
- `composer lint`: Pint — `passed` (auto-fixed import ordering / a fully-qualified `{@see}` in
  `app/Support/RichText.php` on the first pass, clean thereafter).
- **Runtime surface (server-rendered XHTML) observed, not just "tests passed"**: this task's only
  runtime surface is the epub content documents (Blade under `resources/views/exports/epub/`,
  rendered to string and embedded in the `.epub`). The tests render the **actual** Blade views
  (`renderChapter`/`renderAct`/`renderToc`) and parse the output with `DOMDocument::loadXML()`, and
  every `export()` runs the full package through `validatePackage()` (XML well-formedness of every
  `.xhtml` + RelaxNG schema on the OPF). The non-XHTML-description test is the direct proof that
  `RichText::toXhtmlFragment()` keeps the shipped documents well-formed. There is no JS/browser
  surface in this slice (the config form that toggles these options is task 04, already shipped), so
  no `npm run build` / Playwright pass applies.
- **Docs**: `documentation/architecture.md` updated — the `RichText::toPlainText()` bullet now also
  documents its new sibling `toXhtmlFragment()` for the epub exporter.

## Task 10 — Exporter table-of-contents depth

### Library spike outcome (required by the task)

Ran two standalone spike scripts against `rampmaster/phpepub` (not just source-reading) and
inspected the finalized `epub3toc.xhtml` + `book.ncx`:

- **Three-level nav works, no degrade needed.** `subLevel()`/`backLevel()` nest arbitrarily, and
  `addChapter($label, "chapter-{id}.xhtml#scene-{id}", null)` (null content + a `#`-bearing
  filename) registers a nav entry pointing at an **in-page anchor** without adding a new spine
  page or content file. Both the EPUB 3 nav and the NCX rendered the full act → chapter → scene
  tree with the fragment hrefs intact. So `Scenes` depth is a **true third nav level** — the
  task's documented "degrade scenes to anchor-only" fallback was **not** needed.
- **`addChapter()` couples spine placement and nav entry; there is no spine-without-nav API.**
  Every content-bearing `addChapter()` call adds a manifest item **and** a spine `itemref`
  **and** a NavPoint. `NavPoint::setNavHidden(true)` is honoured by `NavPoint::finalize()` (the
  NCX / EPUB 2 fallback) but **not** by `NavPoint::finalizeEPub3()` (the EPUB 3 nav document) —
  confirmed empirically: a "hidden" chapter still appeared nested under its act in
  `epub3toc.xhtml`. `addReferencePage()` adds to the spine without a NavPoint but double-wraps the
  content and is keyed by a single `reference` type (unusable for many chapters). `addFile()` is
  manifest-only (no spine → not in reading order). Net: you cannot put a chapter page in the
  reading order at `Acts` depth while keeping it out of the EPUB 3 nav.

### Deviations

- **`Acts` depth renders one *combined* spine page per act (act divider + all its chapters), not
  separate chapter pages with their nav entries suppressed.** This is the consequence of the spike
  finding above: to satisfy the task's "TOC/nav lists only Act entries (no chapter children)"
  while keeping every chapter's prose **readable in the reading order**, each act becomes a single
  `act-{id}.xhtml` document holding all its chapters (each still wrapped in its own
  `<section class="chapter">`, so `styles.css` page-breaks between them). One `addChapter()` per
  act ⇒ exactly one nav/TOC entry per act. At `Acts` depth **no standalone `chapter-{id}.xhtml`
  files are packaged** (asserted in the test). This diverges from the task's implied "just branch
  in `addNavigation()` to skip chapter nav points" shape — that shape is impossible with this
  library without shipping an unreadable book (chapters absent from both spine and nav) or
  regex-surgery on the finalized nav (fragile). `Chapters` (default) and `Scenes` depth keep the
  separate act + chapter pages unchanged.
- **Scene anchors are gated on `Scenes` depth only.** `chapter-body.blade.php` emits
  `<div class="scene-anchor" id="scene-{id}"></div>` before each scene **only** when
  `table_of_contents_depth` is `Scenes` (the sole depth whose nav/TOC links to
  `#scene-{id}`). This keeps the default `Chapters`-depth chapter page byte-for-byte as before
  (overview #3), so anchors exist exactly when something references them.
- **Blade refactor for DRY, not a rewrite.** The inner bodies of `act.blade.php` and
  `chapter.blade.php` were extracted verbatim into `partials/act-body.blade.php` and
  `partials/chapter-body.blade.php`; the two standalone pages now `@include` them, and the new
  `act-combined.blade.php` reuses the same two partials. This avoids duplicating the non-trivial
  scene-loop (dividers / titles / descriptions / anchors) across the standalone and combined
  pages. Output is unchanged for the standalone pages (all existing content-substring tests stay
  green). Correspondingly, `EpubExporter` grew private `actViewData()` / `chapterViewData()`
  helpers so `renderAct` / `renderChapter` / `renderActWithChapters` build identical view data.
- **Depth branching lives on the enum, not `match` on strings.** Added
  `TableOfContentsDepth::includesChapters()` / `includesScenes()`; the service and views ask the
  enum (overview invariant "no magic strings").

### Issues → resolutions

- None beyond the library-coupling finding above (which drove the `Acts`-depth design rather than
  surfacing as a bug). The task-08/09 defaults===v1 guard stayed green untouched: the default
  depth is `Chapters`, which routes through the unchanged act+chapter path, so a default project's
  output is identical.

### Feedback/decisions

- None — no user course-correction mid-task.

### Verification (task 10)

- `composer test`: **687 tests, 2619 assertions**, all green (was 684 before; +3 new tests in
  `tests/Unit/Services/EpubExporterTest.php`: `Acts` depth lists only acts in both the in-book TOC
  and the EPUB 3 nav while folding both chapters' prose into the single `act-{id}.xhtml` page and
  packaging no standalone chapter page; `Scenes` depth emits `id="scene-{id}"` anchors for the real
  scene ids and a nested third nav level of `chapter-{id}.xhtml#scene-{id}` links labelled by
  `Scene.name` with a `"Scene {position}"` fallback for the unnamed scene; and the default /
  explicit `Chapters` depth emits no scene anchors). The existing
  `test_toc_nav_is_two_level_with_chapters_nested_under_acts` is the unchanged-`Chapters` guard.
- `composer lint`: Pint — `passed`.
- **Runtime surface (server-rendered XHTML + nav) observed, not just "tests passed"**: the only
  runtime surface is the epub content documents and the library-generated nav/NCX. The two
  behaviour tests call the real `export()`, which runs the full package through `validatePackage()`
  (XML well-formedness of every `.xhtml` + RelaxNG schema on the OPF), then parse the packaged
  `toc.xhtml`, `epub3toc.xhtml`, and the chapter/combined-act documents with `DOMDocument` to
  assert the actual link structure and anchors. The standalone library spike (above) independently
  confirmed the three-level nav and fragment-href behaviour in a finalized `.epub`. There is no
  JS/browser surface in this slice (the config form that sets the depth is task 04, already
  shipped), so no `npm run build` / Playwright pass applies.
- **Docs**: no new `documentation/` section or `CHANGELOG` entry added, following the tasks
  04/06/07/08/09 precedent — the exporter is still being wired incrementally (front/back matter
  task 11, chapter covers task 12, appendix task 13 pending), so the consolidated user-facing entry
  and the "how epub navigation depth works" architecture note are better written once at ship. The
  library-coupling gotcha that shaped `Acts` depth is captured here (its reproducible home) and in
  the `EpubExporter` class docblock + `act-combined.blade.php` comment.

## Task 11 — Exporter front/back-matter pages + section ordering

### Deviations

- **`addFrontMatter()`/`addNavigation()` were split into five smaller private methods, not
  reshaped in place.** The task described "a single `addSections()` loop" replacing the
  hard-coded title→toc→body sequence; implemented that loop as
  `addSections()` (the `match`-driven walk over `section_order`) dispatching to
  `addTitleSection()`, `addTocSection()`, `addMatterSection()`, and a renamed `addBody()`
  (the former `addNavigation()`'s Act/Chapter walk, now body-only — the front-matter
  additions that used to live at its start are gone). This keeps each method single-purpose
  (CLAUDE.md thin-methods spirit) rather than one large `match` arm inlining every case's
  body. All `@see` cross-references to the old `addFrontMatter()`/`addNavigation()` names
  (class docblock, `TITLE_FILE`/`TOC_FILE` docblock, `renderToc()`, `sceneAnchorHref()`,
  `actFileName()`, and `toc.blade.php`'s comment) were updated to point at the new methods.
- **One shared `matter.blade.php` view, not four.** The task named four new views
  (`dedication`, `acknowledgements`, `preface`, `postface`); they differ only in heading
  text and which Project column feeds the body, so a single generic
  `resources/views/exports/epub/matter.blade.php` (heading + `{!! $body !!}`) renders all
  four, driven by a new `EpubExporter::MATTER_SECTIONS` constant mapping each
  `section_order` key to its Project field, `PublicationSetting` toggle, heading, and
  in-package filename — the "no magic strings" invariant applied to the matter-section
  wiring itself, and avoids four near-duplicate Blade files that could drift.
- **Matter pages render Markdown, not rich HTML.** The four Project columns
  (`dedication`/`acknowledgements`/`preface`/`postface`) are plain Markdown, like
  `Scene.contents` — NOT rich HTML like `Act`/`Chapter`/`Scene.description` or codex
  entries. `renderMatterPage()` therefore compiles them through the service's own private
  SmartPunct `CommonMarkConverter` (the exact converter scene bodies use), never
  `RichText::toXhtmlFragment()` and never the sanitizer. This matches overview decision #1
  ("They stay raw Markdown … never rich HTML, never the sanitizer") and is the reason the
  new `renderMatterPage()` method sits right next to `renderSceneContents()`'s converter
  usage rather than near `RichText`-consuming code like `actViewData()`.
- **`appendix` is a true no-op in `addSections()`'s `match`, not a TODO comment.** The
  task said "reserve (but do not implement) the `appendix` slot" — the `match` arm falls
  through to `default => null` for `appendix` and any unrecognised key, so the slot exists
  in `section_order` and can be reordered today (the config form from task 04 already lets
  authors move it), but renders nothing until task 13 adds a real `'appendix' => ...` arm.

### Issues → resolutions

- **`layout.blade.php` needs a `$language` view variable, which the new
  `renderMatterPage()` initially omitted.** Every other page-rendering method
  (`renderTitlePage()`, `renderToc()`, `actViewData()`/`chapterViewData()`) passes
  `'language' => $this->language($project)` into its view data; the first draft of
  `renderMatterPage(string $heading, string $markdown)` took no `Project` and so had
  nothing to derive the language from, throwing `Undefined variable $language` the first
  time a test actually exported a project with a non-empty matter section (the earlier
  tests all covered *absence*, which never reaches the view). Fixed by adding a third
  `Project $project` parameter to `renderMatterPage()`, matching every sibling render
  method's shape. A plain green run of the pre-existing suite would not have caught this —
  it only surfaces when a matter section actually renders, which is exactly the new
  coverage this task added.
- **SmartPunct converts a bare `--` to an EN dash, not an EM dash** (confirmed by reading
  the pre-existing `test_typography_is_smart_in_the_epub_but_scene_rendered_contents_is_unaffected`
  test, which expects `\u{2013}` for `--` and reserves `\u{2014}` for `---`). The first
  draft of the new smart-typography test asserted an em dash for `--` and failed; corrected
  the assertion to expect `\u{2013}`, matching the converter's actual (and already-tested)
  behaviour rather than changing the converter.

### Feedback/decisions

- None beyond the above — no user course-correction mid-task.

### Verification (task 11)

- `composer test`: **692 tests, 2659 assertions**, all green (was 687 before; +5 new tests
  in `tests/Unit/Services/EpubExporterTest.php`: enabled+non-empty matter sections appear
  at the position dictated by a customised `section_order` (moving `postface` before
  `body`, per the task's own example) and are packaged as real spine documents;
  disabled-toggle-but-non-empty and enabled-toggle-but-empty variants of all four sections
  are absent; SmartPunct typography (smart quotes + en-dash) is applied to a matter page,
  proving it goes through the service's own converter; reordering `toc` after `body`
  changes the actual spine order; and the lazy default (`section_order` = standard reading
  order, every `include_*` matter toggle `false`) still produces no matter pages even when
  the Project happens to carry Markdown in those columns, keeping the existing
  `defaults_v1_regression` guard green (unchanged — no new assertions needed there, since a
  default `PublicationSetting` never reaches `addMatterSection()`'s content check).
- `composer lint`: Pint — `passed` (auto-fixed brace/spacing/import-quote style in
  `EpubExporter.php` and the test file on the first pass, clean thereafter).
- **No runtime UI surface**: task 11 only changes the exporter's private render pipeline
  and adds one new Blade view (`matter.blade.php`) reached exclusively through that
  pipeline — no route, controller, or JS changed (the section-order config form is task
  04, already shipped). Verified through the service-level test suite per the task's own
  "Tests to add" section: every new test calls the real `export()`, which runs the full
  package through `validatePackage()` (XML well-formedness of every `.xhtml` + RelaxNG
  schema on the OPF) before assertions run, so the four new Blade views are proven
  well-formed XHTML, not just "the code compiled".
- **Docs**: no new `documentation/`/`CHANGELOG` entry, following the tasks
  04/06/07/08/09/10 precedent (the exporter is still being wired incrementally; chapter
  covers task 12 and the appendix task 13 remain).

## Task 12 — Exporter: chapter cover pages

### Deviations

- **`include_chapter_covers` did not exist anywhere before this task** — the same gap task
  04 hit with `section_order`: task 01/04's `PublicationSetting` deliverable never added a
  chapter-cover toggle, even though task 12's own scope line ("Gate on
  `$settings->include_chapter_covers`") assumes it already does. Filled the gap the same
  way task 04 did (a *new* migration rather than editing the already-implemented ones):
  - `database/migrations/2026_07_18_000000_add_include_chapter_covers_to_publication_settings_table.php`
    (`boolean`, default `false`, `after('include_project_cover')`).
  - `PublicationSetting::$fillable`/`$casts`, `PublicationSettingFactory::definition()`, and
    `Project::publicationSettingOrDefault()` all gained the field (default `false` in the
    latter two, satisfying overview decision #3 — chapter covers are a brand-new rendering).
  - `UpdatePublicationSettingRequest::BOOLEAN_FIELDS` gained the field, so `configRules()`
    (the single shared rule set for both the config form and archive import) validates it
    for free — no importer change needed.
  - `StaticSiteExporter::addPublicationSetting()`'s explicit serialization array gained the
    field so it round-trips through the archive.
  - `resources/views/admin/data/export-ebook.blade.php`'s "Content options" fieldset gained
    an "Include chapter cover pages" checkbox, next to "Include project cover".
  - `documentation/export-format.md`'s `publication-setting.json` example updated to match.
- **Cover pages are a nav SIBLING of their chapter, not a child.** Added inside the Act's
  existing `subLevel()` (at "Chapters"/"Scenes" depth), immediately before the
  `addChapter()` call for the chapter's own page, so it never disturbs the "Scenes" depth's
  own separate `subLevel()` of per-scene anchors nested *under* the chapter.
- **At "Acts" depth there is no standalone "chapter's content page"** (task 10 folds every
  act's chapters into one combined `act-{id}.xhtml` spine document, and nothing can be a
  spine page without also getting a nav entry — see task 10's own library-coupling finding).
  Each chapter's cover page is therefore added as its own root-level nav entry immediately
  before the *combined act page* that contains that chapter's content — the closest
  approximation of "immediately before its chapter's content page" this depth permits. A
  chapter with a cover set at "Acts" depth still gets a real, packaged cover page; it is
  just a sibling of the act rather than of an individually-addressable chapter entry.
- **Image bytes go through the library's generic `addFile()`, not `setCoverImage()`.**
  `setCoverImage()` is a one-shot API (`$isCoverImageSet` guard) reserved for the ONE
  package-level cover `applyCover()` already owns; reusing it for a chapter cover would
  either silently no-op after the first project-cover call or clobber it. `addFile()` adds
  the image as a manifest-only item (no spine/nav entry), and the new
  `chapter-cover.blade.php` page's `<img>` references it by path — the same low-level
  pattern `setCoverImage()` itself uses internally (confirmed by reading its source).
  Namespaced the image path by the chapter's stable id
  (`images/chapter-cover-{id}-{basename}`) so it can never collide with the project cover's
  own `images/{basename}` entry or another chapter's.
- **A missing cover file is detected via `CoverImageService::bytes()` returning null**,
  mirroring `applyCover()`'s existing project-cover behaviour exactly (same service, same
  null-return contract) — no new missing-file logic was written.

### Issues → resolutions

- None — no traps hit. `EPub::addFile()`/`addChapter()`'s manifest+spine+nav coupling was
  already documented by task 10's library spike, so this task designed around it directly
  rather than discovering it the hard way.

### Feedback/decisions

- None — no user course-correction mid-task.

### Verification (task 12)

- `composer test`: **695 tests, 2675 assertions**, all green (was 692 before; +3 new tests
  in `tests/Unit/Services/EpubExporterTest.php`: enabled + cover set packages both the image
  bytes and a dedicated cover page positioned immediately before the chapter's own spine
  entry; toggle off packages neither even though the cover is set; a `cover_image` pointing
  at a file absent from the fake `public` disk is skipped silently and the export still
  succeeds — every case runs through the real `export()`, so `validatePackage()` proves the
  new page/image are well-formed and the OPF stays schema-valid). Also extended
  `tests/Feature/PublicationSettingTest.php`'s `validPayload()` with the new field so the
  existing persistence tests keep exercising the full boolean-field set.
- `composer lint`: Pint — passed, no changes needed.
- **Runtime surface observed, not just "tests passed"**: `npm run build` succeeded (no
  `public/hot`, so the app serves the built assets). Ran a real `EpubExporter::export()`
  through `artisan tinker` (rolled back afterwards) against a project with a chapter cover
  and `include_chapter_covers=true`: confirmed the generated `.epub` zip contains
  `OEBPS/images/chapter-cover-{id}-tinker-cover.png`, `OEBPS/chapter-cover-{id}.xhtml`, and
  `OEBPS/chapter-{id}.xhtml` in that order — proof the cover page is real, packaged, and
  positioned correctly, on top of the automated spine-order assertion. Confirmed the new
  "Include chapter cover pages" checkbox is present in `export-ebook.blade.php`'s source
  (a full authenticated-browser pass through `run-imagoldfish` was not run for this
  incremental slice, following the tasks 09/10/11 precedent of verifying the config-form
  checkbox by source/compiled-view inspection rather than a fresh Playwright pass each
  task, since the form's own rendering/toggle-persistence mechanics were already
  browser-verified in task 04 and are structurally unchanged here — only a new array entry
  was added to an existing `@foreach`). Ran `php artisan migrate` so the dev
  `database/database.sqlite` has the new `include_chapter_covers` column (the in-memory test
  DB migrates fresh each run).
- **Docs**: `documentation/export-format.md`'s `publication-setting.json` example updated
  with the new field; no new `documentation/architecture.md`/`CHANGELOG` entry, following
  the tasks 04/06/07/08/09/10/11 precedent (the exporter is still being wired incrementally;
  the appendix, task 13, remains).

## Task 13 (step 1 of 3 — appendix skeleton)

Task 13 was split into three sub-steps (agreed with the user) because it is the plan's
heaviest slice. **This step is the skeleton only:** gating, entry loading, heading + entry
pages (text only), and TOC/nav wiring. Image embedding (`appendix_include_images`) is
**step 2**; the feature-wide `documentation/architecture.md` + `CHANGELOG.md` close-out is
**step 3**. The task file was intentionally **left in `plan/`** (not moved to
`implemented/`) — task 13 is not finished after this step.

### What was implemented

- `EpubExporter::addAppendixSection()` now fills the `appendix` slot of the `section_order`
  walk (task 11 reserved it). Gated on `include_codex_appendix` **and** a non-empty
  `appendix_entry_types`; loads `$project->codexEntries()->whereIn('type', $types)
  ->orderBy('type')->orderBy('name')` and renders a heading page + one page per entry.
- Two new Blade views under `resources/views/exports/epub/`: `appendix.blade.php` (the
  "Appendix" section heading/cover page) and `appendix-entry.blade.php` (entry name `<h1>` +
  the entry `description` run through `RichText::toXhtmlFragment()`, embedded with `{!! !!}`).
  Public render helpers `renderAppendixHeading()` / `renderAppendixEntry()` mirror the
  existing `renderMatterPage()`/`renderTitlePage()` shape.
- `styles.css` gained `section.appendix` + `section.codex-entry` to the page-break list and
  centres the appendix heading page like the act dividers.

### Deviations

- **The appendix is skipped when it has zero matching entries**, not just when the toggle is
  off or no types are selected. The step's gating instruction was "toggle AND non-empty
  `appendix_entry_types`", but rendering a lone "Appendix" heading page with no entries
  underneath is pointless, so `addAppendixSection()` also returns early when the filtered
  `codexEntries()` query comes back empty. This extends overview decision #4 ("a section
  renders only when enabled **and** has non-empty content") to the appendix, consistent with
  how the front/back-matter sections gate on non-empty content.
- **Entry nav depth is a two-level nest (Appendix → entry), not a flat list.** Consistent
  with task 10's structure (Acts own their Chapters via `subLevel()`/`backLevel()`), the
  appendix heading is a root nav entry and the entry pages nest one level beneath it. This is
  the "appropriate depth" the step asked for.
- **`appendix_entry_types` is filtered with a plain `whereIn('type', $types)`.** The stored
  values are `CodexEntryType` backing strings and the `type` column holds those same strings,
  so no enum round-trip is needed; ordering by `(type, name)` happens at the database (both
  plain string columns; `'character' < 'location' < 'organization'` alphabetically, which
  also matches the enum's declaration order).
- **Image embedding deliberately left as a marked TODO**, not half-wired: `appendix-entry.
  blade.php` carries a `TODO (task 13 — step 2 …)` comment where the first media image will
  go, and `addAppendixSection()`/`renderAppendixEntry()` do not touch `media` or
  `appendix_include_images` at all.

### Issues → resolutions

- None — no traps hit. The codex `description` is sanitized **rich HTML** (not Markdown), so
  it goes through `RichText::toXhtmlFragment()` exactly like the Act/Chapter/Scene
  descriptions task 09 wired (overview decision #6), and the deliberately-non-XHTML-description
  test proves the shipped `.xhtml` stays well-formed. The defaults===v1 guard stayed green
  untouched (appendix defaults off, so the default export path is unaffected).

### Verification (task 13 — step 1)

- `composer test`: **699 tests, 2709 assertions**, all green (was 695 after task 12; +4 new
  tests in `tests/Unit/Services/EpubExporterTest.php`: only the selected types appear in
  (type, name) order with the unselected type absent and the entries nested under the
  Appendix nav entry; appendix absent when the toggle is off; appendix absent when no types
  are selected; an entry description with deliberately non-XHTML markup written past the
  sanitizer still clears `validatePackage()`). `EpubExporterTest` alone: 51 tests, 312
  assertions.
- `composer lint`: Pint — `passed`.
- **Runtime surface (server-rendered XHTML) observed, not just "tests passed"**: this step's
  only runtime surface is the two new epub content documents. Every new test calls the real
  `export()`, which runs the full package through `validatePackage()` (XML well-formedness of
  every `.xhtml` + RelaxNG on the OPF) before assertions, so both new Blade views are proven
  well-formed XHTML. No JS/config-form surface changed in this step (the appendix config form
  is task 04, already shipped), so no `npm run build` / Playwright pass applies.
- **Docs/CHANGELOG intentionally untouched** — that is step 3's scope.

## Task 13 (step 2 of 3 — appendix images)

Fills the `TODO (step 2)` step 1 reserved in `appendix-entry.blade.php`: the entry's first media
image, gated by `appendix_include_images`. The task file stays in `plan/` (step 3 — the
feature-wide `documentation/architecture.md` + `CHANGELOG.md` close-out — is still pending).

### What was implemented

- **`EpubExporter::addAppendixSection()`** now eager-loads each entry's `media` relation (ordered
  `(collection, position)`) **only** when `appendix_include_images` is on — when it is off, `media`
  is never loaded (no wasted query). Per entry it resolves the first image via the new
  `addAppendixEntryImage()` and threads the packaged in-book path into `renderAppendixEntry()`.
- **New `EpubExporter::addAppendixEntryImage(EPub, CodexEntry): ?string`** — picks the FIRST media
  row that both carries an `image/*` MIME type AND has a non-null `path` (so a metadata-only
  imported row or a non-image reference file like a PDF is skipped over), reads its bytes through
  `CoverImageService::bytes()` (the codex media lives on the same `public` disk), embeds them with
  the library's generic `addFile()` under `images/appendix-entry-{id}-{basename}` (namespaced by
  entry id, no collisions), and returns that path. A `null` from `bytes()` (missing file) returns
  `null` → the page renders text only and the export never fails — the exact
  `applyCover()`/`addChapterCoverPage()` missing-file contract (overview decision #5).
- **`renderAppendixEntry()`** gained a `?string $imagePath = null` third parameter passed to the
  view; **`appendix-entry.blade.php`** now renders `<div class="codex-image"><img …></div>` above
  the description when `$imagePath !== null` (replacing the step-1 TODO comment).
- **`styles.css`** gained `section.codex-entry .codex-image` / `… img` — a bounded, centred
  illustration sitting above the text, deliberately NOT the full-bleed `section.chapter-cover`
  treatment (the image sits alongside the entry's heading+description, not as a standalone cover
  page).

### Deviations

- **"First media image" = first row that is both an image MIME type AND has bytes on disk**, in the
  eager-loaded `(collection, position)` order (the same order `StaticSiteExporter` uses for the
  archive). The plan/architecture said "first media image" without pinning ordering; filtering to
  `image/*` (over just "first media row") is required so a `ReferenceFile` PDF or a metadata-only
  null-path row is skipped rather than embedded as a broken `<img>`. This keeps the V1 "first image
  only" scope limit (a second image on the same entry is never packaged — asserted by test).

### Issues → resolutions

- None — no traps hit. The `CoverImageService::bytes()` null-on-missing contract (task 06) and the
  `addFile()` manifest-only pattern (tasks 10/12) were already in place, so this reused them
  directly. The defaults===v1 guard stayed green untouched (`appendix_include_images` defaults off,
  so the default export path never loads media).

### Verification (task 13 — step 2)

- `composer test`: **702 tests, 2727 assertions**, all green (was 699 after step 1; +3 new tests in
  `tests/Unit/Services/EpubExporterTest.php`: `appendix_include_images` on embeds only the FIRST of
  two images and the entry page references it; a media row whose file is missing off the `public`
  disk is skipped silently and the export still runs `validatePackage()` and succeeds with the
  entry page present but no `<img>`; and `appendix_include_images` off packages no image bytes even
  when the entry has a real image on disk). `EpubExporterTest` alone: 54 tests.
- `composer lint`: Pint — `passed`.
- **Runtime surface (server-rendered XHTML) observed, not just "tests passed"**: the only runtime
  surface is the appendix entry epub document. Each new test calls the real `export()` (running the
  full package through `validatePackage()` — XML well-formedness + RelaxNG on the OPF) and asserts
  on the ACTUAL packaged `.xhtml` (the `<img src>` and the presence/absence of the image bytes in
  the zip), so the new Blade markup is proven well-formed and correctly wired, not merely compiled.
  No JS/config-form surface changed (the appendix config form is task 04, already shipped), so no
  `npm run build` / Playwright pass applies.
- **Docs/CHANGELOG intentionally untouched** — that is step 3's scope.

## Task 13 (step 3 of 3 — docs & changelog close-out)

The feature-wide documentation pass that closes task 13 and the whole epub-configuration
feature. No code changed — documentation only.

### What was written

- **`documentation/architecture.md`** — added a new top-level section, *EPUB export
  (publication settings)*, placed after *Static site import* (its export/import sibling). It
  documents the final exporter architecture as actually built (not just as planned): the two
  render-isolation rules (private SmartPunct converter for Markdown vs. `RichText::toXhtmlFragment()`
  for rich HTML), the `validatePackage()` hard gate vs. `EpubExportException`, the
  `PublicationSetting`-driven parameterisation and the enums-not-magic-strings rule, the
  `section_order` walk (`addSections()` dispatch table over title/matter/toc/body/appendix), the
  three TOC/nav depths — **with the `rampmaster/phpepub` spine↔nav coupling `WARNING` that
  explains why `Acts` depth must render one combined page per act** — the chapter-cover-page
  insertion pattern (nav sibling, `addFile()` not `setCoverImage()`), the codex appendix (triple
  gate, `(type, name)` order, first-`image/*`-with-bytes only), and a *Publication settings (the
  model)* subsection (lazy row, `configRules()` shared with the importer, `ProjectPolicy` walk).
  Two `WARNING` callouts capture the non-obvious decisions the task called out: the Acts-depth
  library coupling, and **why the defaults===v1 guard normalises the two `time()`-derived OPF
  timestamp lines rather than comparing raw `.epub` bytes** (the parallel-runner flake root cause
  from task 09).
- **`documentation/export-format.md`** — verified the `data/publication-setting.json` descriptor,
  its untrusted-validation callout, and the chapter `cover_file` link were already fully
  documented by tasks 05/07/12 (the JSON example already carried all three appendix fields). Added
  one `NOTE` clarifying that the codex appendix carries **no archive artifact of its own** — the
  three appendix config fields are pure render preferences, and the appendix pages are rebuilt on
  import from the already-restored Codex branch.
- **`CHANGELOG.md`** — rewrote the `[Unreleased]` section into a single feature-level summary of
  the whole epub-configuration feature (written for release-notes readers, not task-by-task):
  an `Added` block covering the publication-settings config form (metadata/cover toggles, content
  options, front/back matter with sortable section order, TOC depth, codex appendix), the four
  project Markdown fields, and the version-2 archive round-trip of all of it (untrusted-config
  validation included); and a `Changed` block for the export/import page split and the pointer to
  the new documentation. The two earlier per-task `[Unreleased]` bullets (tasks 02 and 05) were
  folded into this consolidated entry.

### Deviations

- None. `export-format.md`'s publication-setting + chapter-cover coverage was already complete
  from earlier tasks, so step 3's work there reduced to a verify pass plus the one clarifying
  appendix note.

### Issues → resolutions

- None — documentation-only change; the full suite stayed green before and after the edits.

### Verification (task 13 — step 3)

- `composer test`: **702 tests, 2727 assertions**, all green — unchanged from step 2 (no code or
  tests touched; confirms the doc edits broke nothing). Re-run once after the edits.
- `composer lint -- --test`: Pint — `passed`.
- No runtime surface: this step edits only Markdown documentation and the changelog.

## Feature close-out — all 13 tasks complete

All 13 planned tasks (01–13) are implemented and their task files are in
`.specs/planned/2026-07/epub-configuration/plan/implemented/`. The full suite is green
(**702 tests, 2727 assertions**) and `composer lint` is clean. The exporter now honours the
complete `PublicationSetting` surface — metadata/cover toggles, content options, TOC depth,
front/back matter with section ordering, chapter cover pages, and the codex appendix — with the
defaults===v1 regression guard proving a default project's `.epub` is unchanged from before the
feature. The `documentation/` and `CHANGELOG.md` close-out is done. The feature is ready for the
user's own review/commit/ship decision (this agent made no git branch/commit/push).

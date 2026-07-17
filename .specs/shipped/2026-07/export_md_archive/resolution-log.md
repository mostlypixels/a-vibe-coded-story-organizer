# Export to static files — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

Decisions from the planning grill (these reshaped the design substantially from the
original `expanded/` docs — the plan supersedes them):

- **Two-layer export.** The zip has two top-level folders: `book/` (human reading) and
  `data/` (lossless machine export). The original single lossy-HTML tree and
  `storyline.html` are dropped.
- **Lossless & reimportable.** `data/` is built to be reimported into *this same app*
  later. **Import is not in this feature** — we only produce an export capable of it.
- **No duplication between layers.** Each entity in `data/` is a directory with one raw file
  per content field (`contents.md`, `description.html`, `notes.html` = exact stored column
  values, fragments) plus a `*.json` of scalars + stable DB ids + relationship id-lists +
  links to the field files.
- **Full menu scope.** Story (acts/chapters/scenes), Timeline (plotlines/events), and Codex
  (entries/attributes/tags/media) are all exported.
- **Attribute-over-time is crucial** and is preserved as
  `entry.json.attribute_values[] = {attribute_id, start_event_id, value}`.
- **Stable IDs = DB primary keys**; directory names `<id>-slug` are cosmetic.
- **`data/` layout**: grouped by type; nesting mirrors ownership;
  `data/codex/attributes.json` + `data/tags.json` are flat lists; `data/project/` +
  `data/manifest.json` (`version: 1`).
- **`book/` layout**: `index.html` TOC (acts+chapters titles, linked); `book/NN/` act folders
  (zero-padded position); `book/NN/NN.html` chapters (zero-padded, per-act position) =
  chapter title + scene `contents` (Markdown→HTML) joined by `<hr>`, no scene titles;
  prev/next crossing act boundaries, ends → `index.html`; minimal inline CSS.
- **Media toggle** labelled **"Include images & files"** governs all `codex_media` bytes
  (cover + reference images + reference files); `media[]` metadata is always written.
- **Excluded**: scene `share_token` / `share_expires_at`.
- **Bookend events + main plotline** are exported; matching-not-duplicating them is a future
  *import* concern (documented, not handled now).
- **Delivery**: synchronous in-request build now, but via an **HTTP-agnostic
  `StaticSiteExporter`** so a future queued Job can reuse it; everything works from the web
  UI with **no CLI step** (bytes read off the disk, not the `/storage` URL, so no
  `storage:link` needed).
- **Zip name**: `<project-slug>-<Ymd-His>.zip`.
- **Authorization**: admin gate **plus** `authorize('view', $project)`; foreign/missing id → 403.
- **`ext-zip`** added to `composer.json`.

## Deviations from the spec/plan

Intentional narrowings from the original `spec.md`, confirmed with the user (do **not**
re-add without a new decision):

- **No rendered act/chapter pages.** The original spec's per-act and per-chapter `index.html`
  (name + rendered description) are dropped. Acts/chapters appear in the human `book/` layer
  only as **titles** (TOC + chapter heading); their descriptions live only as raw
  `data/.../description.html`.
- **No rendered scene "all fields" page.** The original scene `.html` ("html of the scene and
  of all the other fields") is dropped. `book/` renders only scene **contents** (prose);
  scene description/status/event exist only raw in `data/` (notes are private).
- **No human-readable Codex or Timeline.** "Arborescence matches the menu" is honored only in
  the machine `data/` layer. There are **no rendered/browsable** Codex or Timeline pages;
  `book/` is story-prose only.

Rationale: the human layer is deliberately scoped to "the manuscript, readable" (`book/`);
everything else is preserved losslessly in `data/` for a future reimport. The user explicitly
chose this tradeoff.

## Issues → resolutions

### Task 01 — endpoint, form, exporter skeleton

- **Task Tests bullet contradicted the binding authorization design; followed the invariant.**
  The task's Tests section lists "missing `project_id` → `assertSessionHasErrors('project_id')`",
  but that cannot happen with the specified `ExportRequest::authorize()`. A Form Request runs
  `authorize()` **before** `rules()` validation, and the spec's `authorize()` resolves
  `Project::find($project_id)` — for a missing id that is `Project::find(null) === null` → returns
  false → **403**, so validation never runs and no session error is set. This matches binding
  invariant 1 ("foreign **or missing** `project_id` → 403") and the task's own `ExportRequest`
  spec ("foreign/missing id = 403"). Resolution: implemented `authorize()` as specified and wrote
  the test as `test_a_missing_project_id_is_forbidden` (403), plus a `nonexistent_project_id`
  403 case. The `include_images` validation test still asserts session errors (a valid, owned
  project makes `authorize()` pass so validation runs). No production code deviated from the
  plan — only the test expectation for the missing-id case was corrected to match the invariant.

- **`$errors`-undefined warning when rendering the panel outside HTTP (verification artifact, not
  a bug).** A bare `view('admin.data.index')->render()` in `artisan tinker` warns "Undefined
  variable $errors" / "Call to a member function get() on null" because `$errors` is shared by the
  web-group `ShareErrorsFromSession` middleware, which a bare render bypasses. Confirmed harmless
  by rendering the page through the full HTTP kernel (`Kernel::handle`) instead: the form renders
  correctly with CSRF token, populated project `<select>`, and the checkbox. Feature tests that GET
  the route through the HTTP stack also pass. No code change needed.

### Task 02 — data/ Story branch (project + acts + chapters + scenes)

- **Session-limit interruption, resumed cleanly.** The task-02 `plan-implementer` agent was
  terminated by a session/quota limit mid-run — *after* writing the production code
  (`StaticSiteExporter::addStory`/`addProject`/`addScene`/`addFieldFile`/`entityDir`) and the 8
  Story-branch tests, but *before* finishing the `export-format.md` Story section (left as a
  "Coming in later tasks" stub) and before moving the task file to `implemented/`. On resume the
  working tree was inspected, the code + tests were found complete and green (18 `ExportTest`
  cases pass), the missing docs section was written (field-file convention, layout tree,
  `scene.json` shape, id-recording note, share-column exclusion), Pint was confirmed clean, and
  the full suite re-run. No production code was changed on resume — only the docs were completed.

### Task 03 — data/ Timeline branch (plotlines + events)

- **No `position` column on plotlines — ordered by `name`.** The task said "plotlines by
  `name` (or `position` if present)". Confirmed via the `create_plotlines_table` migration
  (and its `is_main`/`color` add-column migrations): plotlines have no `position` column, so
  iteration is `orderBy('name')`. Events order by `(event_datetime, id)` — the same canonical
  tie-break `Project::startEvent()`/`endEvent()` use. Ordering affects file iteration only,
  never identity.

- **Events have `title`, not `name`, so `entityDir(Model)` couldn't be reused as-is.**
  Extracted a small `slugDir(int $id, string $name)` helper; `entityDir()` now delegates to it
  for name-based entities, and the event branch calls `slugDir($event->id, $event->title)`
  directly. No behavior change for the existing Story branch (Pint + the 18 prior tests stay
  green). This keeps the `<id>-slug` scheme single-sourced instead of duplicating the sprintf.

- **`event_datetime` cast serialization.** The `datetime` cast yields a Carbon instance;
  serialized with `->toIso8601String()` (guarded with `?->` for defensiveness though the
  column is non-null). Verified the real export emits `"2026-05-01T09:30:00+00:00"`.

- **Reused every task-02 helper unchanged.** `addFieldFile` (null-omit rule), `addJson`,
  `addFromString`, `slug`, `entityDir` — no duplication. `addTimeline` is wired into `export()`
  after `addStory`, guarded by the same try/catch temp-file cleanup.

### Task 04 — data/ Codex branch (entries, attributes, tags, media + toggle)

- **Media `file` path scheme decided from the task's examples.** The task showed
  `cover/portrait.jpg`, `reference-images/01-sketch.png`, `reference-files/01-notes.pdf`. From
  those: the single **cover** keeps its bare original name (no prefix); the multi-item
  **reference** collections prefix a **zero-padded position** (`%02d`) so two files sharing an
  `original_name` never collide inside the entry dir. Encoded in a `mediaFilePath()` match on
  `CodexMediaCollection`. The `original_name` is `basename()`-guarded so a stray path component
  in a stored filename can never escape the entry directory in the zip.
- **Missing-on-disk media byte is skipped, not fatal.** When `includeMedia` is on,
  `Storage::disk('public')->get($path)` returns `null` for a file that has gone missing; the
  export skips writing that byte file rather than aborting the whole archive, while the row's
  `media[]` metadata (which is unconditional) still records that the media existed. A green
  suite would not have caught an uncaught-null-write here — verified the happy path copies real
  bytes via the tinker run below.
- **`applies_to` serialized from the `AsEnumCollection` cast.** `CodexAttribute::applies_to` is
  an `AsEnumCollection` of `CodexEntryType`; `attributes.json` writes the list of enum **values**
  (`->map(fn ($t) => $t->value)`), guarded with a null-fallback to `[]` for defensiveness though
  the column is non-null in practice. Matches the enum-value convention already used for scene
  `status` and entry `type`.
- **Entry iteration order.** `CodexEntry` has no `position` column (unlike acts/chapters/scenes),
  so entries iterate by `id`; grouping is by `type` in the directory path. Attribute values within
  an entry are ordered `(codex_attribute_id, start_event_id, id)` for a stable file. Order only
  affects file-write order, never identity (the DB id).
- **Reused every prior-task helper unchanged** — `entityDir`, `addFieldFile` (null-omit rule),
  `addJson`, `addFromString`. `addCodex` is wired into `export()` after `addTimeline`, inside the
  same try/catch temp-file cleanup. No duplication of the `<id>-slug`/field-file machinery.

### Task 05 — book/ reading layer (TOC + compiled chapters)

- **Numbering uses the raw `position` value, zero-padded — not a re-sequenced ordinal.** The
  binding layout says "zero-padded act **position**" and "zero-padded **per-act** chapter position".
  A single `chapterHref(Act, Chapter)` helper does `sprintf('%02d/%02d.html', $act->position,
  $chapter->position)` and is the one source for the TOC link, the prev/next links, and the written
  zip entry, so they can never drift. Chapter positions are already per-act in this app (auto-assigned
  within the act), so act 2's first chapter is `02/01.html`, not a global `03` — reordering positions
  renumbers on the next export. A green suite wouldn't catch the three-way drift risk; centralizing the
  path in one method (rather than re-deriving it at each call site) removes it structurally.
- **book/ is loaded independently of the data/ Story branch — deliberately, not wastefully.**
  `addStory` calls `$project->load(...)` (mutating the model's relations for the data/ tree with
  `mentionedEvents` etc.); `addBook` runs its own `loadBookTree` query (acts → chapters → scenes,
  only `contents` needed for reading). Sharing the loaded tree would couple the two layers and drag
  the data/-only eager-loads into the reading layer; a second lightweight query keeps them decoupled
  per the plan's "reload or share a private loader" allowance. Ordering is `position` at every level,
  matching book/ numbering and reading order.
- **Titles are escaped, rendered Markdown is not — enforced by where each value flows.** Act/chapter
  titles are plain-text columns, emitted through Blade `{{ }}` (auto-escaped) — verified a title of
  `Act & <em>Ampersand</em>` renders as `Act &amp; &lt;em&gt;Ampersand&lt;/em&gt;`, never raw tags.
  Scene `contents` is the one rendered value, emitted with `{!! Str::markdown($contents ?? '') !!}`
  mirroring the app's existing render path (`story/index`, `shared/scenes/show`). The service passes
  a plain data structure (TOC array; array of raw contents strings) to Blade so presentation logic
  stays in the templates (guidelines) and there is no double-escaping of the rendered HTML.
- **Every scene contributes to the `<hr>` join, including empty ones.** The chapter template renders
  each scene in position order and inserts `<hr>` before all but the first (`@if ($index > 0)`), so
  N scenes yield N−1 rules with no scene titles. A scene with null/empty `contents` still occupies a
  slot (renders as empty) rather than being skipped — matching "each scene's contents joined by
  `<hr>`" literally and keeping the count predictable.

## Verification notes

- Task 01 — Full suite: **285 passed (950 assertions)** via `composer test`; new `ExportTest` = 10
  tests, 28 tests across `ExportTest` + `AdminConfigurationTest`. Pint clean.
- Task 02 — Full suite: **293 passed (1003 assertions)**; `ExportTest` now 18 tests (8 Story-branch
  cases added). Pint clean. Runtime surface: tests build the real streamed zip and open it with
  `ZipArchive`, asserting nested `data/acts/<id>-slug/.../scene.json` paths, `<id>-slug` dir names,
  `scene.json` scalars + `status` enum value + `event_id` + `mentioned_event_ids`, raw unrendered
  `contents.md`/`description.html` bytes, share-column exclusion, null-field omission, and the
  empty-project case.
- Runtime surface: exercised the real download end-to-end — tests open the streamed zip with
  `ZipArchive` and assert `data/manifest.json` (version/project_id/includes_media, both toggle
  states). The Export form was rendered through the full HTTP kernel and visually confirmed
  (CSRF, project selector, "Include images & files" checkbox, submit). `public/hot` is absent, so
  the app serves the built assets. No new JS/build asset was introduced (reuses the existing Alpine
  tab shell + native form controls).
- Task 03 — Full suite: **298 passed (1040 assertions)**; `ExportTest` now 23 tests (5 Timeline
  cases added). Pint clean on the two changed files. Runtime surface: built a real export via
  `StaticSiteExporter::export()` in tinker and inspected the streamed zip's actual entries — the
  Start/End **bookend** events export with `is_fixed: true`, a custom event carries ISO-8601
  `event_datetime` (`2026-05-01T09:30:00+00:00`) + both `plotline_ids` from the pivot +
  `description_file`, the auto-created **main plotline** exports with `is_main: true` (and, having
  no description, correctly omits both `description.html` and the `description_file` key — the
  null-handling rule), and a custom plotline carries `color`/`is_main: false`. No new
  frontend/build surface (pure service + JSON/raw-file zip entries). Timeline branch is Story-branch
  only in shape; no rendered/browsable output (per the plan's human-layer scope decision).
- Task 04 — Full suite: **305 passed (1109 assertions)**; `ExportTest` now 30 tests (7 Codex cases
  added). Pint clean on the two changed files (`StaticSiteExporter.php`, `ExportTest.php`). Runtime
  surface: built a **real export both toggle ways** via `StaticSiteExporter::export($project, …)` in
  tinker and opened the streamed zip with `ZipArchive`. Confirmed against real bytes on the `public`
  disk: `entry.json` carries `type: "character"`, `aliases: ["Ally"]`, `tag_ids`, and the crucial
  `attribute_values: [{ id, attribute_id, start_event_id, value: "29" }]` anchored to the Start event;
  `media[]` lists cover + reference_file with `file: "cover/portrait.jpg"` / `"reference-files/01-notes.pdf"`.
  `data/codex/attributes.json` is a flat list with `applies_to: ["character"]` + `position`;
  `data/tags.json` is flat `{ id, name }`. **Toggle OFF** → `cover/portrait.jpg` byte entry absent
  (`locateName === false`) while `media[]` metadata still present; **Toggle ON** → cover bytes ===
  `"COVERBYTES"` and the non-image reference-file bytes === `"PDFBYTES"` land at their `file` paths
  verbatim (no transform). No `images/manifest.json` is ever written (entry.json IS the manifest).
  The seven feature tests build the same real streamed download through the HTTP endpoint. No new
  frontend/build surface (pure service + JSON/raw-file/media-byte zip entries). Verification records
  were created against the dev DB and then deleted.
- Task 05 — Full suite: **311 passed (1162 assertions)** via `composer test`; `ExportTest` now 36
  tests (6 book/ cases added). Pint clean on the changed PHP + Blade files. Runtime surface (this is
  rendered output, not just JSON, so tests alone were not treated as sufficient): built a **real
  export** via `StaticSiteExporter::export()` in tinker (two acts, three chapters, multi-scene
  chapters) and opened the streamed zip's actual `book/` HTML. Confirmed against real bytes:
  `book/index.html` is a full self-contained document (inline CSS, no external assets) whose `<title>`
  and TOC escape a project/act/chapter name of `Verify & <Render>` / `Act & <em>…</em>` to
  `&amp;`/`&lt;…&gt;` (never raw tags), and links each chapter to `01/01.html`, `01/02.html`,
  `02/01.html` (per-act, zero-padded). The middle chapter page `book/01/02.html` carries prev
  `../01/01.html` and next `../02/01.html` — the next link **crossing into the sibling `02/` act
  folder** — with the nav block present at **both top and bottom**, the chapter title as an escaped
  `<h1>`, and scene `contents` **rendered Markdown → HTML** (`**dark**` → `<strong>dark</strong>`,
  `*ran*` → `<em>ran</em>`) with no scene titles. Empty-project export emits a valid `book/index.html`
  ("This book has no chapters yet.") and zero `book/NN/*` pages. These are static server-rendered
  files with **no JS/build asset** — `@vite`/`public/hot` are not involved, so a stale dev-server
  could not affect them; the pages open directly from the unzipped archive.

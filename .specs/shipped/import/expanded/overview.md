# Import — overview

## Problem statement

`documentation/export-format.md` already documents a **lossless** `.zip` export
(`data/` = machine copy, `book/` = human reading copy) produced by
`App\Services\StaticSiteExporter` and reachable from **Admin → Export & import → Export**
(`resources/views/admin/data/index.blade.php`). The **Import** tab of that same page
currently just says "Importing a backup will be available soon." This feature builds
the importer that reads an export `.zip` back into a brand-new `Project` owned by the
current user.

Import is explicitly a **reconstruction from `data/`** — `book/` is presentation-only
and is ignored entirely, exactly as the export docs specify.

## Goals

* Accept a `.zip` produced by the export feature (or a hand-built one — see Security)
  and recreate the whole project graph: Project → Acts → Chapters → Scenes, the
  Timeline (Plotlines, Events), and the Codex (entries, aliases, tags, attributes,
  attribute values, media).
* Preserve every relationship recorded in `data/` by remapping the archive's stable
  ids (the exported primary keys) onto freshly-inserted rows — never reuse the
  archive's ids directly, since they belong to the exporting installation.
* Preserve `position` ordering exactly as recorded (Acts/Chapters/Scenes), rather than
  re-deriving it from insertion order.
* Never collide with an existing project: on a name collision, the new project is
  still created, disambiguated (see [open-questions.md](open-questions.md) open question 1
  for what "disambiguated" means concretely).
* Treat the upload as **untrusted input**: validate the zip's structure, every file's
  type, and sanitize every piece of content before it is ever persisted or rendered,
  regardless of whether the archive was produced by this app's own exporter.
* The imported project is always created for `$request->user()` — there is no
  "import on behalf of" or "import as," matching the CrawlerSetting-style
  any-authenticated-user exception noted in `CLAUDE.md` (there is no existing project
  to walk up to at authorization time).
* **Work for non-technical installs by default, but let technical installs run it in
  the background.** This app is expected to be installed by users who don't run a
  queue worker — synchronous-in-the-request is the only mode guaranteed to work
  everywhere, so it stays the default. An admin who *has* set up a worker can opt
  into background processing via a toggle (see `architecture.md` → `ImportSetting`).
* **Never lose an import to a mid-run crash.** A synchronous import can be killed by
  a PHP/web-server timeout on a large archive; a queued one can be killed by a worker
  restart. Either way, import progress is checkpointed so a crashed import can be
  **resumed** (continue from the last completed phase) or **discarded** (cleanly roll
  back whatever was partially created) rather than left as an orphaned half-imported
  project with no way to finish or clean it up. See `data-model.md` → *Checkpointing
  & resumability* and `architecture.md` → *The `Import` tracking record*.

## Non-goals

* **Round-tripping the `book/` folder.** It is never read; only `data/` is authoritative.
* **Merging into an existing project.** Import always creates a new project; there is
  no "update project X from this archive" mode.
* **Cross-version migration beyond the documented `version` gate.** If
  `data/manifest.json`'s `version` isn't the one this importer understands, the import
  is rejected with a clear error — no best-effort partial import of an unknown layout.
* **Importing epub or arbitrary third-party zips.** Only the `data/`-contract format is
  understood; anything else fails validation (see `architecture.md` for the specific
  checks).
* **A full background job dashboard.** The queued path reuses the same `Import`
  tracking record and a plain status list on the Import tab — not a general-purpose
  job-monitoring UI.
* **Automatic retry of a crashed import.** Resuming or discarding a stalled import is
  a deliberate, user-initiated action (a button click) — nothing auto-retries in the
  background unprompted.

## User stories

* As a project owner, I can go to **Admin → Export & import → Import**, choose a
  `.zip` file I previously exported (or received from a collaborator), submit it, and
  land on the newly created project with everything — manuscript, timeline, and
  codex — intact.
* As a project owner who already has a project with the same name, importing a second
  archive with that name still succeeds and produces a distinctly-identified project,
  rather than failing or silently overwriting.
* As an attacker who has modified an export archive (renamed files, added an extra
  entry with a path-traversal name, embedded a `<script>` in a "sanitized" HTML
  fragment, or renamed an executable to `.md`), my upload is rejected before anything
  is written to disk or the database — the importer does not trust that a `.zip`
  claiming to be an export was actually produced by `StaticSiteExporter`.

## Acceptance criteria

* A `.zip` exported by `StaticSiteExporter` (both with and without media bytes) can be
  round-tripped: import it, then compare the new project's structure/content against
  the source project (same acts/chapters/scenes in the same order and content, same
  plotlines/events, same codex entries/aliases/tags/attributes/media).
* Re-importing the same archive twice produces two separate projects, not a conflict
  or an overwrite.
* An archive with a `data/manifest.json` `version` this importer doesn't support is
  rejected with a user-facing error, not a partial import or a 500.
* An archive that isn't a valid zip, is missing `data/manifest.json`, contains a file
  outside the expected `data/` arborescence, contains a file whose extension doesn't
  match its actual content (e.g. a `.png` that is really a PHP script), or contains
  Markdown/HTML with disallowed content, is rejected with a specific, actionable
  validation error — never a generic crash.
* A non-owner (i.e. any request without a valid session) cannot reach the import
  endpoint; every import is attributed to the authenticated user regardless of what
  the archive's `project_id` says (that id is only ever used for id-remapping, never
  to decide ownership).
* The project's invariants continue to hold after import: exactly one `is_main`
  plotline, exactly two `is_fixed` events (Start/End), every ordered entity has a
  contiguous `position` sequence within its sibling scope.
* With background processing off (the default), submitting the Import form runs the
  whole import inline and redirects to the finished project, exactly as a
  non-technical single-user install requires with no worker running.
* With background processing on, submitting the Import form immediately shows a
  "queued" status (no long request hang), and the project appears once the queued
  job finishes.
* If an import is interrupted partway through (simulated in tests by throwing after a
  given phase), the `Import` record reflects the last completed phase, nothing
  earlier than that phase is duplicated on resume, and the user can either resume it
  to completion or discard it and have every partially-created row/file cleaned up.

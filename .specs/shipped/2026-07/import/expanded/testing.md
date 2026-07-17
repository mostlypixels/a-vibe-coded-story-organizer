# Import — testing

Follow the existing feature-test style (`tests/Feature/ProjectTest.php` and friends):
plain PHPUnit, `RefreshDatabase`, model factories, `actingAs($user)`, `route()` helper.
A new `tests/Feature/ImportTest.php` (or `ImportProjectTest.php`, matching the
`*Test.php` naming already used) covers the controller/request/happy-path level; the
zip-structure edge cases belong in a dedicated unit-level suite for
`ArchiveValidator`/`ContentSanitizer` (`tests/Unit/Import/...`) since they don't need
the HTTP layer or the database.

## Round-trip (the core correctness guarantee)

* Export a seeded project (acts/chapters/scenes, a non-main plotline, a non-fixed
  event, a codex entry with aliases/tags/attribute values/media) via
  `StaticSiteExporter`, then import the resulting zip, and assert the new project's
  full graph matches the source: same act/chapter/scene names, `contents`,
  descriptions, notes, `position` order; same plotline/event data (except the
  reconciled main plotline / Start-End bookends, which should carry the archive's
  `name`/`title`/`color`/`description` but the *new* project's ids); same codex
  entries/aliases/tags/attribute-values/media metadata.
* Repeat with `includes_media = false` and assert `CodexMedia` rows still exist with
  correct metadata but no byte file is created, and nothing errors trying to read a
  file that isn't there.
* Import the same archive **twice** and assert two distinct `Project` rows are
  created (not a conflict, not an update of the first) — and that the second
  project's `name` carries the timestamp-suffixed disambiguation (open-questions.md
  question 1).

## Authorization

* Guest (no `actingAs`) hitting `POST admin.data.import` gets redirected to login /
  a 401/403 depending on the app's existing auth-middleware behavior — assert
  whichever the codebase's other admin routes already return for a guest, for
  consistency.
* Any two different authenticated users importing the same archive each get their own
  project, owned by `auth()->user()`, regardless of the archive's original
  `project_id` — assert the new project's `user_id` is the importing user's id, never
  the source project's owner.

## Validation failures (`assertSessionHasErrors('archive')`)

* Not a zip (e.g. upload a `.txt` renamed to `.zip`, or a genuinely corrupt zip).
* Zip missing `data/manifest.json`.
* `data/manifest.json` with an unsupported `version`.
* Zip containing a path-traversal entry name (`../../etc/passwd`-style or an absolute
  path) — assert it's rejected and, importantly, that opening/extracting it never
  writes outside the scoped temp import directory (a filesystem-level assertion, not
  just an HTTP one).
* Zip containing a file outside the allow-listed arborescence (e.g. an extra
  `data/../shell.php`, or a `.htaccess` at the root).
* A media file whose real content doesn't match its declared type/collection (rename
  a small PHP file to `.jpg` and reference it as a `cover` in `entry.json`).
* A `contents.md`/`description.html`/`notes.html` whose rendered output contains a
  disallowed tag (`<script>`, `<iframe>`, an `on*=` handler, a `javascript:` URL) —
  assert the import is rejected rather than the tag being silently stripped or (worse)
  persisted.
* Archive exceeding `ImportSetting::current()->max_archive_kilobytes`.

## Import settings (`ImportSetting`)

* Owner-only (any-authenticated-user, per the admin-gate convention) can `PATCH
  admin.data.import-settings`; assert a guest is redirected/denied.
* Saving a new `max_archive_megabytes` value persists the converted
  `max_archive_kilobytes` on the singleton, and a subsequent import attempt is
  validated against the **new** cap (not a stale cached value) — covers the
  "not memoised" requirement in `architecture.md`.
* A fresh install with no `import_settings` row yet still enforces the
  `config('import.default_max_archive_kilobytes')` default via
  `ImportSetting::current()`'s lazy-create path (mirrors `CrawlerSetting::current()`'s
  own test coverage).

## Queued vs synchronous dispatch

* With `ImportSetting::current()->run_in_background = false` (the default),
  `POST admin.data.import` creates the project inline within the same request/test —
  assert no job was pushed to the queue (`Queue::fake()` + `assertNothingPushed()`).
* With `run_in_background = true`, the same request pushes a `ProjectImportJob`
  (`Queue::fake()` + `assertPushed(ProjectImportJob::class)`) and redirects with the
  "Import queued." status **without** the project existing yet; then manually
  running the faked job (or calling `ProjectImporter::run()` directly) completes it.
* Regardless of the toggle, an archive that fails `ArchiveValidator`/`ContentSanitizer`
  is rejected synchronously and **never** reaches the queue — assert
  `assertNothingPushed()` even with `run_in_background = true`.

## Checkpointing, resume, and discard

* Force `ProjectGraphImporter` to throw partway through (e.g. stub the Codex phase to
  throw after Story succeeds) and assert: the `Import` row's `phase` reflects the
  last **completed** phase (`story`, not `codex` or `pending`), the `Project` and its
  Acts/Chapters/Scenes exist in the database (committed, not rolled back), and no
  Codex rows exist yet (that phase's transaction rolled back).
* Calling `resume()` on that same `Import` completes the remaining phase(s) without
  re-creating anything from the already-committed phases (assert row counts for
  Acts/Chapters/Scenes are unchanged by the resume, only Codex rows are newly added).
* Calling `destroy()` (discard) on a stalled `Import` deletes the partially-created
  `Project` (cascade covers its Acts/Chapters/Scenes/etc.), removes the stored
  `archive_path` file off disk, and removes the `Import` row itself — assert all
  three.
* A non-owner cannot `resume()`/`destroy()` another user's `Import` (403, via the new
  `ImportPolicy`) — the negative-case test `CLAUDE.md` requires for every
  authorization check.

## Domain invariants (must hold after every successful import)

* Exactly one `Plotline` with `is_main = true` on the new project (not two).
* Exactly two `Event`s with `is_fixed = true` (Start/End), not duplicated.
* Every `Act`/`Chapter`/`Scene`/`CodexMedia` has a contiguous, correctly-scoped
  `position` sequence after import (assert the exact archive order was preserved, not
  just "some valid sequence").
* Every cross-reference resolves: no `Scene.event_id`, `CodexAttributeValue.start_event_id`,
  etc. pointing at an id that doesn't exist in the new project (i.e. the id-remapping
  in `data-model.md` was applied everywhere, not partially).

## Regression seed

Per `CLAUDE.md`, any bug found while building this ships with a test that fails
before the fix — expect this to matter most around the zip-slip / arborescence checks
and the main-plotline/bookend reconciliation, both of which are easy to get subtly
wrong.

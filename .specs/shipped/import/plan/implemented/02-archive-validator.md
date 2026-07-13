# Task 02 — `ArchiveValidator`

## Scope

The security gate: given a path to an uploaded zip, verify it's a real zip, its
entries are all safe and expected, its manifest version is supported, every JSON
descriptor has the required shape, and every declared media file's actual content
matches what it claims to be. **Does not** extract into the importer's working
directory or write anything to the database — this task's tests call
`ArchiveValidator` directly against fixture zips and assert pass/fail plus the
specific error message.

* `app/Support/ImportRules.php`: `SUPPORTED_MANIFEST_VERSIONS = [1]`, the allow-listed
  arborescence patterns (`data/manifest.json`, `data/project/**`, `data/acts/**`,
  `data/timeline/**`, `data/codex/**`, `data/tags.json`, plus `book/**` and
  `README.md` allowed-but-ignored), `DEFAULT_MAX_ARCHIVE_KILOBYTES = 204800` (used
  only by task 01's migration default/seed, not read at validation time — that reads
  `ImportSetting::current()`).
* `app/Exceptions/ImportValidationException.php`: carries a single user-safe message
  (no internal paths/stack traces) — mirrors `EpubExportException`'s role.
* `app/Services/Import/ArchiveValidator.php`, one method per the 6 checks in
  `architecture.md`'s `ArchiveValidator` bullet:
  1. Valid zip (`ZipArchive::open()` return code).
  2. No zip-slip (normalize every entry name; reject `..`, absolute paths, anything
     resolving outside the archive root) — checked **before** any extraction.
  3. Arborescence allow-list (`ImportRules`'s patterns) — anything else fails.
  4. `data/manifest.json` present, parses, `version` in `ImportRules::SUPPORTED_MANIFEST_VERSIONS`.
  5. Every JSON descriptor decodes and has its required keys (per
     `documentation/export-format.md`'s shapes for `project.json`/`act.json`/
     `chapter.json`/`scene.json`/`plotline.json`/`event.json`/`entry.json`/
     `attributes.json`/`tags.json`).
  6. Every `media[].file` referenced in an `entry.json` exists in the zip, its size
     matches (within tolerance), and its **actual** content (via `finfo_file()`/
     `getimagesize()` on the extracted bytes) matches its declared `mime_type`/
     `collection` — reject any mismatch (e.g. a renamed `.php`).

Each failure throws `ImportValidationException` with a specific, actionable message
identifying which file/rule failed.

## Depends on

Task 01 (for `ImportSetting`, only insofar as the exact size cap isn't this task's
concern — this task validates *structure*, not size; size is enforced by
`ImportProjectRequest` in task 06). No hard runtime dependency on task 01's rows,
but keep the enum/model naming consistent with it.

## Key decisions already made

* Reject-and-report, never best-effort partial validation — the first failure found
  stops validation (or collect all failures if that's cheap; either is fine, but
  never proceed to extraction/import on any failure).
* `book/`/`README.md` are allowed to exist but are never read by this or any later
  task.
* Media content-type checking is by **actual content**, not declared
  `mime_type`/extension — this is the check that stops a renamed executable.

## Docs to consult

`architecture.md` → `ArchiveValidator` bullet (the 6 checks, verbatim);
`documentation/export-format.md` (the exact JSON shapes to validate against);
`testing.md` → *Validation failures* section.

## Tests

One test per failure mode from `testing.md`'s *Validation failures* list, at the
`ArchiveValidator` unit level (build a small fixture zip per case with `ZipArchive`
in the test itself, or a `tests/Fixtures/import/*.zip` set):

* Not a zip / corrupt zip.
* Missing `data/manifest.json`.
* Unsupported `version`.
* Path-traversal entry name (zip-slip) — assert rejection **and** that no file was
  ever written outside a scoped temp path during the check.
* File outside the allow-listed arborescence.
* Malformed/incomplete JSON descriptor (missing a required key).
* Media file whose real content doesn't match its declared type/collection.
* **Happy path**: a real archive produced by `StaticSiteExporter` (export a small
  seeded project in the test, feed the zip straight into `ArchiveValidator`) passes
  with no exception — this is the first point in the plan where export and import
  code paths actually touch, so it's worth asserting early rather than waiting for
  task 09's full round-trip.

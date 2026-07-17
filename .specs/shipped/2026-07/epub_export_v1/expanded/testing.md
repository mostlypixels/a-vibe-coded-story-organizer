# Epub export (v1) — testing

Follow the existing style (`tests/Feature/ProjectTest.php` / the export area's existing
tests): plain PHPUnit, `RefreshDatabase`, factories, `actingAs($user)`, `route()` helper.

## `EpubExportTest` (new feature test, mirrors any existing `ExportTest`)

- **Happy path**: owner posts to `admin.data.export.epub` with a project that has at least
  one Act → Chapter → Scene; response is a successful file download
  (`assertDownload()`/content-type `application/epub+zip`).
- **Authorization**: a non-owner posting a foreign `project_id` gets a 403 (mirrors
  `ExportRequest`'s documented pattern) — the negative case CLAUDE.md requires.
- **Guest** (unauthenticated) is redirected to login, matching the `/admin` gate.
- **Validation — missing content**: a project with zero Acts (or Acts/Chapters that all get
  filtered out — e.g. one Act whose one Chapter has zero Scenes) fails validation with a
  clear error instead of downloading a file (`assertSessionHasErrors()` or equivalent,
  depending on where the "nothing to export" check is implemented — Form Request vs service
  exception surfaced as a redirect-with-error).
- **Skip-empty-chapter invariant**: a project with Act 1 → Chapter 1 (has scenes) and
  Chapter 2 (zero scenes) produces an epub whose TOC/spine contains only Chapter 1 — assert
  on the generated epub's manifest/spine content (unzip and inspect, or assert on the
  `EpubExporter` service's output directly in a unit-level test rather than only through the
  HTTP layer).
- **Skip-empty-act invariant**: an Act with zero surviving Chapters is entirely absent from
  the TOC/spine.
- **Position ordering**: acts/chapters/scenes appear in the epub in `position` order, not
  insertion order (reorder via existing swap logic, then export, then assert order) —
  mirrors the existing `position`-invariant tests on `ActTest`/`ChapterTest`/`SceneTest`.
- **Metadata mapping**: a project with `language`, `author`, `publisher`, `rights`, `isbn`,
  and `cover_image` all set produces an OPF containing each corresponding Dublin
  Core/accessibility field; a project with all of them null produces a valid OPF that simply
  omits the optional ones (title/language/generated-identifier are the only fields present
  when nothing else is filled in).

## `EpubExporter` unit-level tests (service-level, not HTTP)

- Structural validation: feed the service a project, assert the returned file's OPF/nav pass
  `DOMDocument::schemaValidate()` and every content XHTML passes `loadXML()` well-formedness
  — this is the automated safety net itself, so it needs its own direct test independent of
  the HTTP happy-path test.
- Typography: a scene containing `--`, `---`, `...`, and straight quotes produces en-dash,
  em-dash, ellipsis, and curly quotes in the epub's XHTML — and a parallel assertion that
  `Scene::renderedContents` (used elsewhere) is **unaffected**, proving the epub-only
  isolation decision actually holds.
- Act/Chapter page content: assert the exact "Act {position}" + name / "Chapter {position}:
  {name}" text shows up, that Act/Chapter `description` never appears, and that a blank Act
  `name` renders the number-only line (no empty second line).

## `ValidIsbn` rule test

Standard `Illuminate\Contracts\Validation\ValidationRule` test style: valid ISBN-13 (with and
without hyphens) passes; wrong length, non-numeric, and bad-checksum values fail.

## `ProjectTest` additions

- Updating a Project's new metadata fields persists them (happy path).
- Cover image upload/replace/remove follows the same assertions as the existing Codex cover
  tests (stored on the `public` disk, old file deleted on replace, deleted on project
  deletion — extend whatever test already covers `Project`'s `deleting` hook purging Codex
  media, per `data-model.md`'s note that the hook needs to also purge `cover_image`).

## Manual verification (not automatable in PHPUnit)

Per CLAUDE.md's "test the feature working in a browser" guidance and the grilled decision
not to run real `epubcheck` server-side: before shipping, manually download a generated
`.epub` and (a) open it in at least one real e-reader app, and (b) optionally run the actual
`epubcheck` tool locally against it, since the PHP-native validation in `architecture.md`
only checks well-formedness/schema conformance, not the full ruleset epubcheck enforces.

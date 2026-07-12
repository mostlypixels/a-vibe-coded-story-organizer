# Epub export (v1) — plan overview

This is the manual for the task sequence below. It is never itself implemented or moved to
`plan/implemented/`. Read the full `expanded/` set before starting any task; this file only
summarizes what's binding.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-project-metadata-migration` | New `projects` columns (`language`, `author`, `publisher`, `rights`, `isbn`, `cover_image`) + `ValidIsbn` rule. No UI, no epub logic. |
| 02 | `02-project-metadata-ui` | Project edit form gains the six fields (text + cover upload), `UpdateProjectRequest` validation, `ProjectController` cover handling, `Project`'s `deleting` hook purges the cover file. |
| 03 | `03-epub-content-rendering` | `EpubExporter`'s tree loading/filtering (skip-empty-chapter, skip-empty-act) and content generation: Blade views for Act/Chapter XHTML, the page-break CSS, the epub-only smart-typography converter. Produces rendered content in memory — no packaging yet. |
| 04 | `04-epub-packaging` | Add `rampmaster/phpepub`; extend `EpubExporter` to package task 03's content into an actual `.epub` (metadata mapping, accessibility metadata via the library's API, cover embed, generated URN identifier + ISBN secondary identifier). |
| 05 | `05-epub-structural-validation` | Vendor EPUB schema files (from the `epubcheck` project), add the well-formedness + schema-validation pass inside `EpubExporter`, and the `EpubExportException` thrown when the filtered tree is empty. |
| 06 | `06-epub-export-http` | New route, `EpubExportController`, `EpubExportRequest`: wires the HTTP layer to `EpubExporter`, translates `EpubExportException` into a redirect-back-with-error. |
| 07 | `07-epub-export-ui` | Export page's new "Epub export" section (form + submit) and the epubcheck link. |

Dependency graph: 02 depends on 01. 03 depends on 01 (needs `Project.language`). 04 depends on
01 and 03. 05 depends on 03 and 04. 06 depends on 05. 07 depends on 06. (02 has no downstream
dependents other than being the human-facing way to *set* metadata — 04/06/07 all work against
whatever the `Project` row already holds, seeded via factories in tests, so 07 does not
strictly require 02 to be done first, but do it first anyway for a coherent manual test pass.)

## Binding decisions (do not re-litigate)

From `expanded/*.md` and the two grill passes:

- **Packaging library**: `rampmaster/phpepub` (PHP 8.2+, LGPL 2.1, actively maintained fork).
  Not `grandt/phpepub` (dead since 2016) or `andileco/php-epub` (unproven, published days ago).
- **Sync, no queue**: epub generation is a synchronous controller action mirroring
  `ExportController`/`StaticSiteExporter`, same temp-file-then-`deleteFileAfterSend` pattern.
- **Authorization**: `ProjectPolicy@view` via `project_id`, mirrored in both the controller and
  the Form Request's `authorize()` — identical to `ExportRequest`.
- **Content structure**: Act page = "Act {position}" + `Act->name` (name line omitted if
  blank); Chapter page = "Chapter {position}: {name}" + scenes' rendered Markdown, `<hr>`-joined,
  no scene titles. Neither Act nor Chapter `description` is ever rendered.
- **Skip-empty rule**: a Chapter with zero Scenes is omitted (no page, no TOC entry). An Act
  left with zero surviving Chapters is omitted entirely. A project with nothing left after
  both filters throws `EpubExportException`, surfaced as a validation-style error, not a file.
- **TOC**: two-level nav — Acts with nested Chapters.
- **Typography**: `League\CommonMark\Extension\SmartPunct\SmartPunctExtension` (dashes,
  ellipsis, smart quotes together) applied **only** inside `EpubExporter`'s own converter
  instance. `Scene::renderedContents` (used by the Story overview, share page, and the
  existing `book/` export) must remain untouched — every task must grep for and avoid
  modifying that accessor.
- **Markup**: semantic HTML only, plus exactly one CSS file for `break-before: page` /
  `page-break-before: always`. No fonts, colors, or spacing rules.
- **Metadata → OPF**: `dc:title` (`Project.name`), `dc:language` (`Project.language`,
  required, defaults `en`), `dc:creator` (`Project.author`, omitted when null), `dc:publisher`
  (omitted when null), rights (omitted when null), `dc:identifier` = generated
  `urn:imagoldfish:project:{id}` (always present) plus a second `dc:identifier` with
  `opf:scheme="ISBN"` when `Project.isbn` is set. Accessibility metadata
  (`accessibilityFeature`/`accessMode`/`accessibilitySummary`) always present, built via the
  library's native methods, not hand-written XML.
- **Cover image**: reuses `CodexMediaRules::coverRules()` for validation and the `public` disk
  for storage, but is a **plain nullable `cover_image` path column on `projects`** — no
  `CodexMedia`-style tracking table. Cover handling is **inline private methods on
  `ProjectController`**, not a new service class (no second caller exists yet).
- **Validation**: PHP-native only — `DOMDocument::loadXML()` well-formedness on every
  generated XHTML fragment, `DOMDocument::schemaValidate()` on the OPF/nav against schema
  files vendored from the `epubcheck` project. No Java/JVM dependency, no shelling out to a
  real `epubcheck` binary. The export page separately links to
  https://www.w3.org/publishing/epubcheck/ for authors who want full conformance checking.

## Core invariants every task must preserve

- **Position ordering**: acts/chapters/scenes are always read via existing `position`-ordered
  relations (mirrors `StaticSiteExporter::loadBookTree()`), never re-derived or re-sorted.
- **Authorization walks the owning `Project`** via `ProjectPolicy` — never trust route model
  binding or a hidden field alone (CLAUDE.md).
- **`Scene::renderedContents` is the one shared render path for everywhere else in the app** —
  the epub's smart-typography pass must not leak into it.
- **Every new endpoint/controller action/bug fix ships a feature test**, including the negative
  authorization case (CLAUDE.md).

# 03 — Epub content rendering

## Scope

- New `app/Services/EpubExporter.php` (HTTP-agnostic, analogous to
  `app/Services/StaticSiteExporter.php`), covering **only** content generation in this task —
  no packaging library integration yet (that's task 04):
  - Load `$project->load('acts.chapters.scenes')`, position-ordered (mirror
    `StaticSiteExporter::loadBookTree()`'s eager-load shape).
  - **Filter**: drop any Chapter with zero Scenes; drop any Act left with zero surviving
    Chapters. Expose the filtered tree from a method the next tasks (04/05) can call, and that
    tests can assert against directly.
  - **Render**: for each surviving Act, an XHTML string ("Act {position}" + `Act->name`, name
    line omitted when blank, no description); for each surviving Chapter, an XHTML string
    ("Chapter {position}: {name}", no description, followed by its Scenes' Markdown compiled
    to HTML and `<hr>`-joined, no per-scene titles). Rendered via new Blade views under
    `resources/views/exports/epub/` (e.g. `act.blade.php`, `chapter.blade.php`) — HTML is
    never string-built in the service, matching the `book/` layer's own rule.
  - Every rendered XHTML document's root element carries `lang`/`xml:lang` from
    `Project.language`.
  - A dedicated `CommonMarkConverter` instance, configured with
    `League\CommonMark\Extension\SmartPunct\SmartPunctExtension`, used **only** inside this
    service to render scene content for the epub. Do not touch `Scene::renderedContents`.
  - One CSS file (e.g. `resources/views/exports/epub/styles.css` or a PHP string constant —
    decide during implementation) containing only `break-before: page` /
    `page-break-before: always` rules on Act/Chapter root elements. No other styling.

## Explicitly not in scope

- Packaging these documents into an actual `.epub` file, OPF/nav generation, cover/identifier
  metadata (task 04).
- Structural (well-formedness/schema) validation and the empty-project exception (task 05) —
  this task's filtering just produces an empty result set silently; task 05 is what turns that
  into `EpubExportException`.
- The HTTP layer (task 06).

## Depends on

01 (needs `Project.language` to exist for the `lang` attribute).

## Key decisions already made

- Skip-empty-chapter and skip-empty-act are **export-time filtering**, not a persisted
  invariant — no schema change, just service logic (`data-model.md`).
- Act/Chapter `description` fields are **never** rendered on their pages — only
  number/position + name (from the original grill).
- The `SmartPunctExtension` pass (dashes, ellipsis, **and** smart quotes together) is
  epub-only; `Scene::renderedContents` must remain byte-for-byte unaffected.
- TOC/nav generation itself is task 04's concern (it needs the packaging library's nav API) —
  this task only needs to produce the filtered, ordered tree that task 04's nav-builder walks.

## Docs to consult

- `expanded/architecture.md` — the `EpubExporter` responsibilities list and CSS section.
- `expanded/overview.md` — the exact Act/Chapter page content acceptance criteria.
- `app/Services/StaticSiteExporter.php` + `resources/views/exports/book/` — the closest prior
  art for the eager-load shape, the Blade-not-string-building rule, and the `<hr>`-joined
  no-scene-titles content shape.
- `app/Models/Scene.php` (`renderedContents`) — read for reference, do not modify.

## Tests

New `tests/Unit/Services/EpubExporterTest.php` (or `tests/Feature/` if it needs DB factories —
follow whichever convention `StaticSiteExporter`'s own tests use):
- Skip-empty-chapter: an Act with one Chapter containing Scenes and one Chapter with zero
  Scenes → the filtered tree contains only the non-empty Chapter.
- Skip-empty-act: an Act whose only Chapter has zero Scenes → the Act itself is absent from
  the filtered tree.
- Position ordering: acts/chapters/scenes appear in `position` order in the filtered tree and
  in the rendered output, not insertion order.
- Act page content: "Act {position}" + name renders correctly; a blank `name` renders the
  number-only line with no empty second line; `description` never appears.
- Chapter page content: "Chapter {position}: {name}" + `<hr>`-joined scene HTML, no scene
  titles, no `description`.
- Typography: a scene containing `--`, `---`, `...`, and straight quotes renders as en-dash,
  em-dash, ellipsis, and curly quotes in this service's output — **and** a parallel assertion
  that `Scene::renderedContents` for the same scene is unaffected (proves the isolation).
- `lang` attribute: rendered XHTML documents carry the Project's `language` value.

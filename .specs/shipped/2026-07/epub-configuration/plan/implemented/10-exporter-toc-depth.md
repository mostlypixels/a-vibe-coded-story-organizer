# 10 — Exporter: table-of-contents depth

**Model:** Opus (investigative library spike + fallback judgment under uncertainty).

## Scope
Honour `table_of_contents_depth` in **both** the in-book `toc.xhtml` page and the EPUB 3 nav.

- **Spike first:** confirm `rampmaster/phpepub`'s `subLevel()`/`backLevel()`/`addChapter()` support
  a **third** nav level (act → chapter → scene) and fragment hrefs (`chapter-{id}.xhtml#scene-{id}`).
  If it caps at two levels, **degrade "scenes"** to chapter-level nav plus in-page `#scene-{id}`
  anchors in the TOC page (record the outcome in `resolution-log.md`).
- `Acts`: TOC/nav lists only Act entries (no chapter children).
- `Chapters`: today's two-level tree (default — unchanged).
- `Scenes`: add per-scene `id="scene-{id}"` anchors in `chapter.blade.php`; TOC/nav entries link to
  `chapter-{id}.xhtml#scene-{id}`; scene labels use `Scene.name` (fallback `"Scene {position}"`).
- `renderToc()`/`addNavigation()` branch on the enum; chapter labels come from
  `ChapterTitleFormat` (from task 09) so the TOC and headings never drift.

Does **not**: touch front/back matter (task 11) or the appendix (task 13), beyond adding them as
TOC entries if already present.

## Depends on
09 (`ChapterTitleFormat` nav labels + `chapter.blade.php` scene rendering to hang anchors on).

## Key decisions already made
Overview #3/#4. `Scenes` depth has a documented degrade path if the library can't nest three
levels — do not block the feature on it.

## Docs to consult
`../expanded/architecture.md` §B "renderToc/addNavigation", the existing nav code + the library
notes in `EpubExporter`'s class docblock.

## Tests to add
Extend `EpubExporterTest`: `Acts` ⇒ no chapter children in TOC/nav; `Chapters` ⇒ two levels (the
default, unchanged); `Scenes` ⇒ scene anchors exist and resolve to real scene ids in the chapter
document (and, per the spike outcome, either a third nav level or the documented anchor-only
fallback). Package still validates. Defaults===v1 still green.

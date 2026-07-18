# 11 — Exporter: front/back-matter pages + section ordering

## Scope
Render the four Markdown matter fields as epub pages, placed by the sortable `section_order`.

- New Blade views under `resources/views/exports/epub/`: `dedication`, `acknowledgements`,
  `preface`, `postface` — each a heading + the field's Markdown compiled by `EpubExporter`'s
  **private SmartPunct converter** (never `Scene::renderedContents`), `assertXmlWellFormed`'d.
- Drive emission by walking `section_order`: for each key, emit the component **only when**
  enabled (`include_*`) **and** non-empty; `title` (pinned first), `toc`, `body`, and `appendix`
  (task 13) are the other keys — `body` = the existing Act/Chapter walk, `toc` = the in-book TOC
  page, `title` = the title page. Replace the currently hard-coded title→toc→body sequence in
  `addFrontMatter()`/`addNavigation()` with this ordered walk (a single `addSections()` loop).
- Each emitted matter page is a root-level nav entry (its own front/back-matter entry), consistent
  with today's title/toc handling.

Does **not**: render chapter covers (task 12) or the appendix body (task 13 — this task only
reserves the `appendix` slot in the ordered walk).

## Depends on
02 (the Markdown columns), 04 (`section_order` + include toggles persisted), 09/10 (the ordered
walk replaces the title/toc/body sequencing those tasks touch).

## Key decisions already made
Overview #4 (order is data; toggles gate independently; empty ⇒ no page), #1 (Markdown via
SmartPunct). Section membership excludes covers.

## Docs to consult
`../expanded/architecture.md` §B "addFrontMatter/addBackMatter", `../expanded/ui.md` §"New EPUB
Blade views", `../expanded/open-questions.md` Q5.

## Tests to add
Extend `EpubExporterTest`: each enabled + non-empty matter page appears at the position dictated by
a customised `section_order` (e.g. move `postface` before `body`); disabled or empty ⇒ absent;
SmartPunct typography applied (proves the service converter). Reordering `toc`/`body` changes the
spine order. Package validates. Defaults===v1 still green (default order = standard reading order).

# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Entries are grouped under a release heading by change type (`Added` / `Changed` /
`Fixed` / `Removed`) and updated per feature or pull request — not per commit. The
per-commit "why" lives in each commit message body; richer rationale for a change
set belongs in its pull request description.

## [Unreleased]

### Added

- Project theme palette registered in `tailwind.config.js`: `ocean` (#219EBC),
  `aqua` (#8ECAE6), `navy` (#023047), plus `sun` (#FFB703) / `flame` (#FB8500)
  accents, each as a full shade scale.
- `x-table` component family for the striped, sortable index tables (plotlines, events,
  acts, chapters, scenes): `x-table` (card + `<table>` + `head` slot), `x-table-heading`
  (non-sortable header cell), `x-table-row` (striped body row), and `x-table-empty`
  (no-results row). Documented in [`documentation/ui-components.md`](documentation/ui-components.md).
- Reusable UI component library (Blade components in `resources/views/components/`):
  `heading` (unified `<h1>`–`<h6>` scale), `button` (variant/size, renders `<a>` or
  `<button>`), `card`, `badge`, `alert` (dismissible, contextual variants),
  `breadcrumbs` (data-driven), `tooltip`, `popover`, and `dialog` (header/body/footer
  modal built on the existing `modal` shell). Documented in
  [`documentation/ui-components.md`](documentation/ui-components.md).
- Scene status workflow: scenes now carry a `status` (`Draft`, `To Proofread`,
  `To Edit`, `Final`) backed by the `SceneStatus` enum, plus a freeform `notes`
  field. Status renders through a reusable `scene-status-badge` Blade component on
  the scene create/edit/index screens and the story overview.
- Story Overview page (`projects.story.index`) combining the full act → chapter →
  scene tree on one read-only page, with a collapsible table of contents and scene
  contents rendered as Markdown.
- Feature tests for the scene resource (`tests/Feature/SceneTest.php`) covering CRUD,
  authorization, validation, and position auto-assignment; model factories for
  `Act`, `Chapter`, and `Scene`.
- Project coding guidelines (`.claude/guidelines.md`) and a `documentation/` folder
  (architecture, code style, best practices, glossary).

### Changed

- Reskinned the app chrome to the theme palette: the Breeze default indigo accent
  (focus rings, links, active nav, `badge` primary/indigo variants) is now `ocean`;
  primary buttons fill with deep `navy` (higher contrast against their white label);
  and the active-navigation indicator uses the `flame` orange accent. Body text and
  semantic colors (info/success/warning/danger) are unchanged.
- Banded the app header: the top navigation bar is now `navy` (with its logo, links,
  dropdown triggers, and mobile menu lightened to `aqua`/white for contrast) and the
  page-heading bar below it is `ocean-800` (its heading text and back/edit links
  lightened to white/`aqua` via wrapper-scoped selectors in the app layout).
- Index table headers (`<thead>` on plotlines/events/acts/chapters/scenes, plus the
  `sortable-header` component) now use a `sun-400` background with `navy-900` cell
  text for strong contrast.
- Striped table rows are now `gray-100` (a step darker than the previous `gray-50`),
  set once in the shared `x-table-row` component.
- Reordered scenes in the story overview via AJAX (no full page reload).
- Restyled the story overview typography and act headings.
- Consolidated the `MelusineSeeder` chapters into fewer, denser chapters.

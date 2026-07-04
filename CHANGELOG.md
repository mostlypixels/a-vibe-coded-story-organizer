# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Entries are grouped under a release heading by change type (`Added` / `Changed` /
`Fixed` / `Removed`) and updated per feature or pull request — not per commit. The
per-commit "why" lives in each commit message body; richer rationale for a change
set belongs in its pull request description.

## [Unreleased]

### Added

- Codex: a project-scoped reference aggregate for the story's **characters, locations, and
  organizations**. All three share one `codex_entries` table keyed by a `CodexEntryType`
  enum and one `CodexEntryController`, with the kind carried as a `{type}` route segment
  (`characters`/`locations`/`organizations`). Each entry has **aliases**, flat **tags**
  (reusable per project), Markdown **descriptions**, and **media** (a single cover plus
  reference images/files on the `public` disk; cover is the `codex_media` row with
  `collection = Cover`, not a FK). **Temporal attributes** — attribute definitions
  (`codex_attributes`, with an `applies_to` array of entry types) whose values form a
  **start-anchored step function**: each period runs from its anchoring event until the next
  (or the *End* event), so the timeline stays gap-free and a value can be resolved "as of"
  any moment. The new `App\Services\AttributeTimeline` (the project's first `app/Services`
  class) owns resolution and gap-free upserts/removals; `App\Services\CodexMediaService`
  owns file storage, the single-cover rule, and on-disk cleanup. Scene and event pages gain
  **"as of" panels** showing each entry's attribute values at that moment (e.g. a scene during
  *Back to class* shows the character's hair as black). Authorization walks up to the owning
  `Project` (no new policies); a Codex nav dropdown sits between Timeline and Story.
  `MelusineSeeder` seeds a demo set (Mélusine with aliases/tags and a hair-color timeline,
  Raymondin, the Castle of Lusignan, and the House of Lusignan) by calling the timeline
  service directly, since seeding runs with model events disabled.
- Scene ↔ Event links, two relationships. **"Happens during"** — an optional
  `scenes.event_id` foreign key (`nullOnDelete`) placing a scene during a single event;
  chosen on the scene form via a select or an inline "New event" quick-create (auto-attached
  to the Main plotline). **"Mentions"** — an optional `event_scene` many-to-many pivot, edited
  as a checkbox list. Unassigned scenes (no "happens during" event) are flagged with a red
  border on the scenes index and Story overview. Deleting an event unassigns its scenes (via
  the FK) and drops its mention rows (pivot cascade); the event edit page lists the scenes
  happening during / mentioning it. The scene form's "mentions" input is a searchable,
  chip-based event picker (`x-event-picker`, client-side Alpine filter by name/date) rather
  than a checkbox list, so it scales to projects with many events.
- Bookend timeline events: every project is auto-created with two fixed events, "Start"
  (first day of year 0000) and "End" (first day of year 3000), attached to the main
  plotline. Both carry `events.is_fixed` and cannot be deleted (delete button hidden in
  the events index/edit views, `abort_if` guard in `EventController@destroy`), mirroring
  the un-deletable main plotline. `MelusineSeeder` creates them manually since seeding
  runs with model events disabled.
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

- Index tables (plotlines/events/acts/chapters/scenes) now render each row's
  description as small muted text beneath the title instead of a separate
  Description column, and the ordered lists (acts/chapters/scenes) expose their
  `#` position column as a sortable header.
- Index-row edit/delete icon buttons (`x-icon-edit-link`, `x-icon-delete-button`)
  are now outlined: transparent fill with a colored border and matching text —
  `navy-500` for edit, `red-600` (danger) for delete.
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

### Fixed

- The Home/Timeline/Story navigation menu no longer disappears on the event
  edit page: the layout's `$project` resolution chain now also resolves from the
  `event` route parameter (both the desktop bar and the responsive menu).

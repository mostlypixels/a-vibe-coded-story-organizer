# Task 03 — data/ Timeline branch (plotlines + events)

## Scope

Extend `StaticSiteExporter` to write the Timeline portion of `data/`: every plotline and
event in the project, with their relationships, as `<id>-slug` entity directories.

**This task builds:**

- **`data/timeline/plotlines/<id>-slug/`** — `plotline.json`
  (`{ id, name, color, is_main, project_id, description_file? }`) + `description.html`
  (raw fragment; omit when null).
- **`data/timeline/events/<id>-slug/`** — `event.json`
  (`{ id, title, event_datetime: <ISO 8601>, is_fixed, project_id, plotline_ids: [...],
  description_file? }`) + `description.html`.
  - `plotline_ids` from the `event_plotline` pivot (`Event::plotlines`).
  - `event_datetime` serialized as a stable ISO-8601 string (it is a `datetime` cast).
- **Include the auto-created anchors**: the `is_fixed` **Start/End bookend events** and the
  `is_main` **main plotline** are exported like any other row (they're part of the graph and
  `attribute_values.start_event_id` — task 04 — frequently points at the Start bookend).
- Order events by `(event_datetime, id)` and plotlines by `name` (or `position` if present) for
  deterministic output; the ordering only affects file iteration, not identity.
- Slug for an event uses `title` (events have no `name`).
- Append a **Timeline** section to `documentation/export-format.md`, and note the
  bookend/main-plotline **import-time dedup** concern for the future import (the app
  auto-creates them on project creation, so import must match rather than duplicate).

**This task does NOT build:** Story (02), Codex (04), `book/` (05). Codex attribute values
reference `start_event_id`; this task guarantees those event dirs/ids exist in `data/`.

## Depends on

Task **01**. (Independent of 02; can run before or after it. Order in the plan: after 02.)

## Key decisions already made (binding)

- `<id>-slug` dirs, per-entity JSON + raw `description.html` fragment.
- Bookends + main plotline are included in the export (dedup is a future *import* concern,
  documented, not handled here).
- `is_main` / `is_fixed` booleans and `plotline_ids` are part of the lossless contract.
- `event_datetime` as ISO-8601.

## Docs to consult

- `plan/00-overview.md`; `expanded/data-model.md`.
- Models: `app/Models/{Plotline,Event}.php`, `app/Models/Project.php`
  (`startEvent()`/`endEvent()` describe the bookend contract), `app/Support/PlotlineColors.php`.

## Tests (extend `tests/Feature/ExportTest.php`)

- **Plotlines**: assert `data/timeline/plotlines/<id>-…/plotline.json` for a custom plotline
  carries `color` and `is_main: false`; the auto-created main plotline is present with
  `is_main: true`.
- **Events**: an event attached to two plotlines → `event.json` has both `plotline_ids`,
  correct `event_datetime` (ISO), and `is_fixed: false`.
- **Bookends**: the two `is_fixed` Start/End events appear in `data/timeline/events/…` with
  `is_fixed: true`.
- **Description raw**: an event/plotline with a rich-HTML `description` → `description.html`
  holds the exact stored fragment.
- **Empty-ish**: a freshly created project (only bookends + main plotline) still exports a
  valid timeline branch.

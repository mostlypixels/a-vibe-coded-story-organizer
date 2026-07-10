# Task 02 — data/ Story branch (project + acts + chapters + scenes)

## Scope

Extend `StaticSiteExporter` to write the Story portion of the lossless `data/` layer: the
project entity plus the act → chapter → scene tree, each as a `<db-id>-slug` directory with a
per-entity JSON and raw field files.

**This task builds:**

- **`data/project/`** — `project.json` (`{ id, name, description_file: "description.html" }`)
  + `description.html` (raw stored HTML fragment, exact column value; omit the file and the
  link key when the column is null/empty — keep this null-handling rule consistent across all
  entities).
- **`data/acts/<id>-slug/`** — `act.json` (`{ id, name, position, project_id,
  description_file? }`) + `description.html`.
- **`.../chapters/<id>-slug/`** (nested under the owning act) — `chapter.json`
  (`{ id, name, position, act_id, description_file? }`) + `description.html`.
- **`.../scenes/<id>-slug/`** (nested under the owning chapter) — `scene.json`:
  - scalars: `id`, `name`, `position`, `status` (the `SceneStatus` **enum value**, e.g.
    `"draft"`, not the label), `chapter_id`, `event_id` (nullable).
  - relationships: `mentioned_event_ids` (array, from the `event_scene` pivot / `mentionedEvents`).
  - field-file links: `contents_file: "contents.md"`, `description_file?`, `notes_file?`.
  - **Excluded**: `share_token`, `share_expires_at`.
  - field files: `contents.md` (raw Markdown, exact value), `description.html`, `notes.html`
    (raw stored HTML fragments; omit when null).
- Eager-load the tree once (`acts.chapters.scenes.mentionedEvents`, ordered by `position` at
  each level) — no N+1.
- Reuse the `slug()` / `addFromString()` helpers from task 01; add a helper for the
  `<id>-slug` directory name (`sprintf('%d-%s', $model->id, $this->slug($model->name))`).
- Append a **Story** section to `documentation/export-format.md` documenting these JSON
  shapes and the field-file convention.

**This task does NOT build:** Timeline (03), Codex (04), the `book/` layer (05). It writes
`event_id` / `mentioned_event_ids` as raw ids **without** needing the event dirs to exist yet
(a future import resolves them; export just records ids).

## Depends on

Task **01** (exporter skeleton, helpers, endpoint) in `plan/implemented/`.

## Key decisions already made (binding)

- Entity = directory named `<db-id>-slug`; nesting mirrors ownership (invariant, `00-overview.md`).
- Field files hold **raw exact stored values** — never rendered/sanitized (invariant 3).
- Stable ids for every reference; `status` stored as the enum **value** (machine form).
- Share-link columns excluded.
- Null/empty content field → omit both the file and its `_file` link key.

## Docs to consult

- `plan/00-overview.md` (data/ layout, invariants).
- `expanded/data-model.md` (schema facts, still accurate).
- Models: `app/Models/{Act,Chapter,Scene}.php`, `app/Enums/SceneStatus.php`,
  `app/Support/RichTextFields.php` (which fields are rich HTML).

## Tests (extend `tests/Feature/ExportTest.php`)

- **Structure**: given act(pos 1)→chapter(pos 1)→two scenes, assert the nested entry paths
  `data/acts/<id>-…/act.json`, `.../chapters/<id>-…/chapter.json`,
  `.../scenes/<id>-…/scene.json` (+ `contents.md`) exist, with correct `<id>-slug` names.
- **JSON correctness**: `scene.json` carries the right `id`, `position`, `status` **value**,
  `chapter_id`, `event_id`, and `mentioned_event_ids` (seed an event + attach as mentioned →
  assert the id appears); `act.json`/`chapter.json` carry `position` + parent id.
- **Raw field files**: `contents.md` bytes === the stored `contents`; `description.html` ===
  the stored HTML fragment (assert it is NOT wrapped in `<!doctype>` and NOT re-rendered).
- **Exclusions**: a scene with a live share link → `scene.json` has no `share_token`/
  `share_expires_at`.
- **Null handling**: a scene with null `notes` → no `notes.html` entry and no `notes_file`
  key.
- **Empty project**: still 200; `data/project/project.json` present; no `data/acts/*` entries.

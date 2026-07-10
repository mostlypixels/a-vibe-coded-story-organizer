# Task 04 — data/ Codex branch (entries, attributes, tags, media + toggle)

## Scope

Extend `StaticSiteExporter` to write the Codex portion of `data/` — the richest branch —
including the **attribute values anchored to events** (the relationship the user called
crucial) and the co-located media governed by the "Include images & files" toggle.

**This task builds:**

- **`data/codex/<type>/<id>-slug/`** per entry (`<type>` = the entry type value, e.g.
  `character`) — `entry.json`:
  - scalars: `id`, `name`, `type` (enum value), `project_id`, `description_file?`.
  - `aliases`: array of alias strings (from `CodexEntry::aliases`).
  - `tag_ids`: array (from the `codex_entry_tag` pivot).
  - **`attribute_values`**: array of `{ id, attribute_id, start_event_id, value }` (from
    `CodexEntry::attributeValues`) — the attribute-over-time links anchored to events.
  - `media`: array of `{ id, collection, position, original_name, mime_type, size, file }`
    where `file` is the **relative path inside the entry dir** (e.g.
    `cover/portrait.jpg`, `reference-images/01-sketch.png`,
    `reference-files/01-notes.pdf`). **`media[]` is written whether or not bytes are
    copied.**
  - `description.html` field file (raw fragment; omit when null).
- **Media bytes** (only when `includeMedia` is true): copy each `codex_media` file from
  `Storage::disk('public')->get($row->path)` into the entry dir at its `file` path,
  grouped by collection (`cover/`, `reference-images/`, `reference-files/`), preserving the
  `original_name`. Read bytes off the disk directly — never via the public URL (invariant 5).
- **`data/codex/attributes.json`** — the project's attribute **definitions** as a flat array:
  `{ id, name, applies_to: [type values...], position }` (from `Project::codexAttributes`,
  ordered by `position`). No directory (no rich fields).
- **`data/tags.json`** — the project's tags as a flat array: `{ id, name }`.
- Thread `includeMedia` from the controller/service entry all the way here; the manifest's
  `includes_media` (task 01) must equal what this branch actually did.
- Append a **Codex** section to `documentation/export-format.md` (entry JSON, attribute
  values, media layout, the toggle's effect: metadata always present, bytes conditional).

**This task does NOT build:** Story (02), Timeline (03), `book/` (05). It records
`start_event_id`/`attribute_id`/`tag_ids` as raw ids; the referenced event/tag/attribute
records live in their own `data/` locations (this + prior tasks).

## Depends on

Task **01** (and logically 03, which writes the events that `start_event_id` points at —
not required for this task's export code to run, but keep the plan order 01→02→03→04).

## Key decisions already made (binding)

- Media co-locates in the owning entry dir; the entry JSON **is** the manifest (no separate
  `images/manifest.json`).
- Toggle "Include images & files" governs **bytes only**; `media[]` metadata is
  unconditional. Covers all three collections including `reference_file`.
- `attribute_values` embedded under the entry as `{attribute_id, start_event_id, value}`.
- Attribute **definitions** and **tags** are flat JSON lists (no field files).
- Bytes read off the disk, not the `/storage` URL (invariant 5).

## Docs to consult

- `plan/00-overview.md`; `expanded/data-model.md` (image ownership on `CodexEntry`).
- Models: `app/Models/{CodexEntry,CodexMedia,CodexAlias,CodexAttribute,CodexAttributeValue,Tag}.php`,
  `app/Enums/{CodexEntryType,CodexMediaCollection}.php`, `app/Services/CodexMediaService.php`
  (how paths/collections are stored).

## Tests (extend `tests/Feature/ExportTest.php`; use `Storage::fake('public')` + `UploadedFile::fake()`)

- **Entry JSON**: an entry with aliases, tags, and a description → `entry.json` has the right
  `aliases`, `tag_ids`, `type` value, and `description.html` holds the raw fragment.
- **Attribute values (crucial)**: seed an attribute + a value anchored to a specific event →
  `entry.json.attribute_values` contains `{attribute_id, start_event_id, value}` with the
  correct ids. `data/codex/attributes.json` lists the definition with `applies_to` + position.
- **Tags list**: `data/tags.json` contains the project's tags with ids matching the entry's
  `tag_ids`.
- **Media metadata always present**: with the toggle **off**, `entry.json.media[]` still
  lists the cover (collection, original_name, mime, `file`), but the byte file is **absent**
  from the zip (`locateName(...) === false`) and no `images/manifest.json` exists.
- **Media bytes when on**: with the toggle **on**, the cover/reference-image/reference-file
  bytes land at their `file` paths and equal the stored bytes (no transform/thumbnail);
  reference files (non-image) are included too.
- **Authorization/empty** already covered by task 01; add an entry-less project → codex
  branch emits `attributes.json`/`tags.json` (possibly empty arrays) and no entry dirs.

# Import — data model

The manuscript/timeline/codex graph itself writes into the existing schema
(`Project`, `Act`, `Chapter`, `Scene`, `Plotline`, `Event`, `CodexEntry`, `CodexAlias`,
`CodexAttribute`, `CodexAttributeValue`, `CodexMedia`, `Tag`) using the same models and
the same lifecycle hooks the rest of the app relies on — no migration changes there.
Two **new** tables are needed to support checkpointing/settings (see below): `imports`
and `import_settings`. This document covers the **id-remapping**,
**invariant-reconciliation**, and **checkpointing** rules an importer must follow —
the part that isn't just "insert a row per JSON file."

## Source format (recap)

The full contract is `documentation/export-format.md`; this section only restates the
parts that drive import-time decisions.

* `data/manifest.json`: `{ version, project_id, exported_at, includes_media }`.
  **`version` must be checked before anything else is read** — see
  [open-questions.md](open-questions.md) question 5 for the exact supported value(s).
* Every entity directory (`<id>-slug`) holds one JSON descriptor whose `id` is the
  **source installation's** primary key — never reused as the new row's id.
* Content fields (`contents.md`, `description.html`, `notes.html`) are sibling files,
  linked via `*_file` keys, omitted when the field was empty. An importer must treat a
  missing `*_file` key as "field is null/empty," not as an error.
* Cross-references (`event_id`, `mentioned_event_ids`, `plotline_ids`, `tag_ids`,
  `attribute_id`, `start_event_id`, `chapter_id`, `act_id`, `project_id`) are all
  **source ids** and must be resolved through the id-map built during import.

## Id remapping

Build one `array<int, int>` map per entity type (`$actIdMap`, `$chapterIdMap`,
`$sceneIdMap`, `$plotlineIdMap`, `$eventIdMap`, `$codexEntryIdMap`, `$tagIdMap`,
`$attributeIdMap`) as rows are inserted: `$map[$sourceId] = $newModel->id`. Every
subsequent reference (`event_id`, `plotline_ids`, etc.) is looked up in the
appropriate map before being written to the new row. A reference to a source id that
isn't in the map (e.g. a corrupted archive referencing a non-existent event) is a
validation failure, not a silently-dropped relationship — see `testing.md`.

Import order is therefore **dependency order**, not archive traversal order. These
four steps double as the **checkpoint phases** in the next section — each one is a
named `ImportPhase` enum case, committed as its own transaction:

1. **`ImportPhase::Project`** — create the `Project` (triggers the `booted()`
   `created` hook: auto-creates the main plotline and Start/End bookend events for
   the **new** project — capture their ids immediately, before touching the
   archive's timeline data).
2. **`ImportPhase::Timeline`** — `Plotline`s (except the main one — see
   reconciliation below), then `Event`s (except the two bookends — same
   reconciliation), building `$plotlineIdMap`/`$eventIdMap` as they're inserted.
3. **`ImportPhase::Story`** — `Act` → `Chapter` → `Scene`, in that nesting order,
   resolving each scene's `event_id`/`mentioned_event_ids` against `$eventIdMap`
   (already fully populated from phase 2).
4. **`ImportPhase::Codex`** — `data/tags.json` → `Tag` rows (`$tagIdMap`),
   `data/codex/attributes.json` → `CodexAttribute` rows (`$attributeIdMap`), then
   each `CodexEntry` (resolving `tag_ids`), then each entry's `CodexAlias` rows, then
   each entry's `CodexAttributeValue` rows (resolving `attribute_id` and
   `start_event_id` against the maps built in this phase and phase 2), then each
   entry's `CodexMedia` rows (resolving bytes per the media section below).

## Checkpointing & resumability

A new `imports` table (`app/Models/Import.php`) tracks one row per import attempt —
this is what makes a synchronous request-timeout or a queued-worker crash recoverable
instead of a silent orphan:

| Column                | Notes                                                                                   |
|------------------------|------------------------------------------------------------------------------------------|
| `user_id`              | The importing user (FK, cascade-delete) — same ownership rule as everywhere else.        |
| `project_id`           | Nullable FK to the `Project` being built; set as soon as phase 1 commits.                |
| `archive_path`         | The uploaded zip's path on a private disk (`storage/app/imports/<uuid>.zip`) — kept until the import completes or is discarded, **not** deleted immediately like the exporter's temp file, precisely so a crash can resume from it. |
| `phase`                | `ImportPhase` enum (`pending`, `project`, `timeline`, `story`, `codex`, `completed`, `failed`) — the **last successfully completed** phase; resuming starts at the next one. |
| `id_maps`              | JSON: the accumulated `$actIdMap`/`$chapterIdMap`/.../`$attributeIdMap` arrays from `data-model.md`'s id-remapping section, persisted after each phase commits so a resume doesn't need to replay earlier phases to rebuild them. |
| `queued`               | Boolean — whether this import is running via the queued `ProjectImportJob` or inline; drives which UI feedback the Import tab shows. |
| `failure_message`      | Nullable — the safe-to-display message from whichever phase failed (never a raw stack trace, same rule as `ui.md`'s validation-failure feedback). |

Each phase runs inside its **own** `DB::transaction()` (not one transaction for the
whole import) specifically so a crash mid-phase rolls back only that phase's partial
writes, while everything from prior committed phases (and the `imports` row's
`phase`/`id_maps`) survives. `ArchiveValidator` and `ContentSanitizer` (see
`architecture.md`) still run **before phase 1** on the whole archive, so a structural
or content-security failure never gets a chance to leave *any* partial row behind.

**Resume**: re-open `archive_path`, restore `$idMaps` from the `imports` row, and
re-run `ProjectGraphImporter` starting at `phase + 1` — earlier phases are not
replayed since their rows and id maps are already committed and persisted.

**Discard**: delete the `Project` (if phase ≥ 1 had created one — its own cascading
deletes and `booted() deleting` media-purge hooks handle everything under it),
delete `archive_path` off disk, delete the `imports` row. This is a normal, explicit
user action (an admin can't accidentally lose data — see `ui.md`), never automatic.

An import only reaches `phase = completed` after phase 4 commits; at that point
`archive_path` is deleted (nothing left to resume) exactly like the exporter deletes
its own temp zip once sent.

## Reconciling the auto-created invariants

`Project::booted()`'s `created` hook already gives the **new** project its own main
plotline and Start/End bookend events before any archive data is touched. The
importer must not insert a second `is_main` plotline or a second pair of `is_fixed`
events — instead:

* Read the archive's main plotline row (`is_main: true` in `data/timeline/plotlines/`)
  and map its **source id** directly onto the **new project's freshly auto-created**
  main plotline id — copy over the archive's `name`/`color`/`description` onto that
  existing row (an `update`, not a `create`), then continue.
* Read the archive's two `is_fixed` events and map them the same way onto the new
  project's own auto-created Start/End events (resolved via `Project::startEvent()`
  / `Project::endEvent()`), updating `title`/`event_datetime`/`description` in place.
* Every other plotline/event in the archive is inserted normally.

This is the exact reconciliation `documentation/export-format.md`'s "Import-time dedup
concern" note calls for.

## Position handling

Every ordered model's `creating` hook (`HasSiblingPosition`) only auto-assigns
`position` when it is `null`. To preserve the archive's exact ordering, the importer
must **explicitly set `position` from the JSON** before saving each `Act`/`Chapter`/
`Scene`/`CodexMedia` row — never leave it `null` and let the hook re-derive it, since
insertion order during import does't necessarily match archive order once
dependency-ordering (above) is applied. `CodexAttribute.position` is likewise taken
verbatim from `data/codex/attributes.json`.

## Media

`CodexMedia` rows are created from each entry's `media[]` array regardless of whether
byte files are present in the archive (mirroring `includes_media` governing bytes
only, per the export contract):

* If `includes_media` is `true` and the archive contains the referenced file: copy the
  bytes to a **newly generated** storage path on the `public` disk (never reuse the
  archive's `file` path as the stored `path` column — that path is meaningless outside
  the archive), re-validate the file's actual content against `collection` (image
  collections must decode as an image; `reference_file` is checked by the same
  extension/mime allowlist `CodexMediaRules` already enforces for direct uploads).
* If `includes_media` is `false` (metadata-only export), the `CodexMedia` row is still
  created (`original_name`, `mime_type`, `size`, `collection`, `position`) but its
  `path` is left representing "no file" — see `open-questions.md` question 2 for
  exactly how the app should render a `CodexMedia` row with no backing file, since
  this situation doesn't currently occur anywhere else in the app.
* `original_name` is re-derived from `basename()` on import regardless of what the
  JSON says — never trust it as pre-sanitized, per the export doc's own security note.

## Project identity / naming

`Project` has no `slug` column and no unique constraint on `name`
(`database/migrations/2026_07_01_172707_create_projects_table.php`); routes bind
`{project}` by numeric `id`. The archive's `project_id` is used **only** for internal
id-remapping context (it is not written anywhere on the new row) — the new project
always gets a fresh auto-increment id and belongs to `$request->user()`. On a name
collision (case-insensitive match against the importing user's existing projects),
the new project's `name` gets a timestamp suffix, e.g. `"My Novel (imported
2026-07-13 14:32)"` (see `open-questions.md` question 1 — resolved).

# 01 — Data model: `scene_codex_entry` pivot

## Scope

The persisted relationship table and its two Eloquent relations. No matching logic, no
controller wiring — this task only makes "a scene can be linked to codex entries" representable
and queryable.

**Builds:**
- Migration `create_scene_codex_entry_table`: `scene_id` + `codex_entry_id`, both
  `constrained()->cascadeOnDelete()`, composite primary key `['scene_id', 'codex_entry_id']`. No
  `id`, no timestamps — matches the `codex_entry_tag` / `event_scene` pivot convention exactly
  (`database/migrations/2026_07_04_000005_create_codex_entry_tag_table.php` and
  `2026_07_04_000001_create_event_scene_table.php`).
- `Scene::codexReferences(): BelongsToMany` → `CodexEntry`, via `scene_codex_entry`.
- `CodexEntry::referencingScenes(): BelongsToMany` → `Scene`, via `scene_codex_entry`.

**Does NOT build:** the matcher (task 02), any controller wiring (03/04), any UI (05/06).

## Depends on

None — first task.

## Key decisions already made

- No pivot model, no extra columns (which alias matched, timestamps). The pivot is a pure
  derived link, matching `codex_entry_tag`. See `../expanded/data-model.md`.
- `cascadeOnDelete` on both FKs is the **entire** cleanup mechanism for entry/scene deletion —
  no `deleting` hook needed on either model for this pivot.

## Consult

`../expanded/data-model.md`, `00-overview.md`.

## Tests

- A new `tests/Unit/ScenePivotTest.php` or an addition to an existing model test: attach a
  `CodexEntry` to a `Scene` via `codexReferences()->attach()`, assert `referencingScenes()` on
  the entry returns it back (round-trip).
- Deleting the `Scene` removes the pivot row (`assertDatabaseMissing('scene_codex_entry', ...)`,
  the `CodexEntry` row untouched).
- Deleting the `CodexEntry` removes the pivot row, the `Scene` row untouched.

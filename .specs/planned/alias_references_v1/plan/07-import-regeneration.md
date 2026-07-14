# 07 — Import regeneration (and confirming export writes nothing)

## Scope

Because `scene_codex_entry` is a derived cache (see `../expanded/data-model.md`), an imported
project must end up with the same references it would have if every scene/entry had been saved
natively — but the archive itself never carries this data. This task wires that regeneration
into the import pipeline, at the one point where it's actually possible to compute (after both
scenes and codex entries/aliases exist).

**Builds:**
- `App\Services\ProjectImporter::run()` (`app/Services/ProjectImporter.php`): inject
  `SceneReferenceMatcher`, and call `$this->matcher->syncProject($import->project)` once, **after**
  the `foreach ($this->remainingPhases(...) as $phase) { ... }` loop and **before**
  `$import->update(['phase' => ImportPhase::Completed]);`. This point is reached exactly once per
  import that completes, regardless of how many `run()` calls/resumes it took (see
  `../expanded/architecture.md` → *Import/export interaction* for why).
- No `ImportPhase` enum change, no new checkpoint field — the call is idempotent
  (`syncProject()` is always a full resync), so a crash between this call and marking `Completed`
  just means the next `run()` retries it safely.

**Confirms, does not change:** `StaticSiteExporter::addScene()` already excludes any codex
reference data by construction (its `$json` is an explicit field list) — this task adds no code
there, only the `[!WARNING]`-style note already placed in `documentation/export-format.md`
(added during the expand pass) that future editors shouldn't add `codex_entry_ids` to
`scene.json`. Verify that note is present; don't duplicate it.

**Does NOT build:** the matcher itself (task 02), any UI (05/06), the async-rescan follow-up
(out of scope for v1 entirely — see `.specs/draft/alias_references_asynchronous/spec.md`).

## Depends on

- **01** (pivot/relations) in `plan/implemented/`.
- **02** (`SceneReferenceMatcher`) in `plan/implemented/`.

## Key decisions already made

- The resync runs **outside** any of the four phases' own `DB::transaction` blocks — it reads
  already-committed data, exactly like a normal scene/entry save's sync runs after its own
  commit. Don't wrap it in a new transaction of its own; `sync()` on the pivot is already atomic
  per call.
- This is deliberately **not** a fifth `ImportPhase` — it has no `id_maps` contribution and
  nothing in the archive maps onto it (`data-model.md` → *Export/import impact*). Do not add an
  enum case or touch `ProjectGraphImporter`.
- An archive exported before this feature existed (no reference concept at all) must import
  identically to one exported after — since references are never read from the archive either
  way, this is automatic; no version-gating needed in `data/manifest.json`.

## Consult

`../expanded/architecture.md` → *Import/export interaction* (has the exact hook-point code),
`../expanded/data-model.md` → *Export/import impact*, `00-overview.md`.

## Tests

- Extend `tests/Feature/ImportRoundTripTest.php`: export a project containing a scene whose
  contents mention a codex entry's alias/name (so the source project has a
  `scene_codex_entry` row from a native save), import the resulting archive, and assert the
  **new** project's corresponding scene/entry pair also has the row — proving regeneration, not
  copying (the archive itself carries no reference data to copy).
- A scene whose contents, after import, mention an entry that only exists because a **different**
  entry's alias also matches (overlapping-alias scenario) ends up with both links, same as a
  native save would produce.
- Resumability: simulate a crash by manually setting an `Import` row's `phase` to `Codex` (as if
  that phase's `run()` call had crashed right after its checkpoint save) with no
  `scene_codex_entry` rows populated yet, call `run()` again, and assert the references appear
  and the import completes — proving the post-loop hook fires on a resumed call whose
  `remainingPhases()` is empty.
- An import that never reaches the Codex phase (e.g. fails during Story) leaves no partial/stale
  reference rows — nothing to sync yet, nothing to clean up either.

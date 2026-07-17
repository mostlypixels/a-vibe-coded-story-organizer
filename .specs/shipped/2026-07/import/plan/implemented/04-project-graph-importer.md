# Task 04 — `ProjectGraphImporter`

## Scope

The core domain logic: given an **already-validated, already-extracted** `data/`
directory and the importing `User`, insert the full project graph — one method per
`ImportPhase`, each independently callable and each wrapped in its own transaction.
**Does not** handle checkpoint persistence onto the `Import` row, temp-directory
lifecycle, or archive validation/sanitization — those are task 05's job. This task's
tests call `ProjectGraphImporter`'s phase methods directly against a hand-built
extracted `data/` fixture directory (no zip, no HTTP, no `Import` row needed).

* `app/Services/Import/ProjectGraphImporter.php`, one method per phase, each in its
  own `DB::transaction()`:
  * `importProject(string $dataPath, User $user): Project` — reads
    `data/project/project.json` (+ `description.html` via `ContentSanitizer` from
    task 03), creates the `Project` for `$user` (triggers the auto-create hook),
    applies the name-collision suffix (case-insensitive match against the user's
    existing project names → append a timestamp), returns the new `Project`.
  * `importTimeline(string $dataPath, Project $project, array &$idMaps): void` —
    reads `data/timeline/plotlines/*` and `data/timeline/events/*`; for the
    `is_main` plotline and the two `is_fixed` events, **update** the project's
    already-auto-created rows in place (all fields) instead of inserting, and map
    the archive's id onto them; every other plotline/event is inserted normally.
    Builds `$idMaps['plotlines']`/`$idMaps['events']`.
  * `importStory(string $dataPath, Project $project, array &$idMaps): void` — walks
    `data/acts/<id>-slug/` → `chapters/<id>-slug/` → `scenes/<id>-slug/`, inserting
    Act/Chapter/Scene with `position` set explicitly from JSON, resolving each
    scene's `event_id`/`mentioned_event_ids` through `$idMaps['events']`. Builds
    `$idMaps['acts']`/`$idMaps['chapters']`/`$idMaps['scenes']`.
  * `importCodex(string $dataPath, Project $project, array &$idMaps): void` —
    `data/tags.json` → `Tag` rows, `data/codex/attributes.json` →
    `CodexAttribute` rows (position from JSON), then each `data/codex/<type>/<id>-slug/`
    → `CodexEntry` (resolving `tag_ids`) + its `CodexAlias` rows + `CodexAttributeValue`
    rows (resolving `attribute_id`/`start_event_id`) + `CodexMedia` rows (position
    from JSON; bytes copied to a newly generated storage path when present, row
    still created with no file when `includes_media` was false per task 01's
    schema/`open-questions.md` question 2).
  * A reference to a source id not present in the relevant `$idMaps` entry throws
    `ImportValidationException` (task 02) — never silently dropped.

## Depends on

Task 01 (models/enum), Task 03 (content sanitization applied to every
`description.html`/`notes.html`/`contents.md` read during import — call
`ContentSanitizer` inline as each file is read, not as a separate pre-pass, since by
this point the whole archive already passed `ArchiveValidator` in task 02's flow;
this task's own tests can call `ContentSanitizer` directly too).

## Key decisions already made

* Import order is `project → timeline → story → codex` — story resolves against
  `$idMaps['events']`, which must be fully populated (a completed `timeline` phase)
  before story starts.
* Anchor reconciliation copies **every** field the archive recorded, never a partial
  field list.
* `position` is always set explicitly from JSON — never left null for the
  `HasSiblingPosition` hook.
* `original_name` on `CodexMedia` is re-derived via `basename()` on import,
  regardless of what the JSON says.
* Name-collision suffix format: `"<name> (imported <Y-m-d H:i>)"` — case-insensitive
  match against the importing user's existing project names.

## Docs to consult

`data-model.md` (the whole document — this task implements almost all of it);
`documentation/export-format.md` (exact JSON shapes/keys per entity).

## Tests

Build a small, hand-authored `data/` fixture directory (or reuse one exported by
`StaticSiteExporter` in the test setup — either is fine, whichever is less brittle)
covering: an act/chapter/scene tree with a non-trivial `position` order, a non-main
plotline, a non-fixed event, a scene's `event_id` pointing at a Timeline event, a
codex entry with aliases/tags/attribute values (anchored to an event)/media (both
with and without bytes present).

* Each phase method run in order produces the expected rows with correctly remapped
  ids everywhere a source id appeared.
* The archive's main plotline/bookend events update the project's auto-created rows
  in place (assert exactly one `is_main` plotline / two `is_fixed` events remain,
  and their fields match the archive, not the auto-created defaults).
* `position` order survives exactly, including a chapter/scene order that wouldn't
  match plain insertion order.
* A scene's `event_id` resolves to the correct **new** Event id, not the archive's.
* A `CodexAttributeValue.start_event_id` resolves correctly, including when it
  points at the Start bookend.
* `CodexMedia` with `includes_media = false` in the source manifest still creates a
  metadata-only row (no file).
* A reference to a source id absent from the fixture (a deliberately corrupted
  `event_id`) throws `ImportValidationException`, not a silent skip or a DB error.
* Importing the same fixture twice for the same user produces a second `Project`
  whose `name` carries the timestamp suffix.

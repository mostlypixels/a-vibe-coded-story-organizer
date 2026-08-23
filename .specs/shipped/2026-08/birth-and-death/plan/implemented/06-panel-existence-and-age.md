# 06 — Panel existence and age

## Scope

- `CodexAsOfResolver::resolve`:
  1. Drop any entry where `entry->existsAt($moment)` is false (existence filter).
  2. Add an `age` key (`?Age`) to each surviving row from `entry->ageAt($moment)`.
  3. Change the keep-rule so an existing entry with an age but no attribute values still shows
     (keep when it has attributes **or** a non-null age).
  - Eager-load `inceptionEvent` and `terminationEvent` on the entries query (both feed `existsAt`)
    to avoid N+1.
- `resources/views/codex/partials/as-of.blade.php`: render an `Age {{ years }}` line when the
  row's `age` is non-null. No age line otherwise. The partial only draws entities the resolver
  kept.
- Seeding: set at least one Melusine character's inception (and ideally a termination) so the panel
  demonstrates age. Plain column assign in the seeder (`WithoutModelEvents` — no service needed).

## Depends on

02 (`existsAt`, `ageAt`), 04 (columns are saved/settable so tests and seed can link).

## Key decisions

- Existence window symmetric + inclusive; inverted always shows; before/after hidden
  (`open-questions.md` #4, #5, #8).
- Age for every lifespan-tracking type, both the scene and event edit panels (same resolver).

## Consult

`expanded/architecture.md` → As-of resolver; `expanded/ui.md` → panel.

## Tests

Feature, per `expanded/testing.md` → *Scene edit panel* / *Bug-fix guard*:

- Character inception 1980, scene at a 2000 event → panel shows "Age 20".
- Scene before inception → entity absent; scene after termination → entity absent.
- Existing entry with an inception and zero attribute values → present, with its age (the
  fails-before-fix guard for the keep-if-age change).
- Inverted lifespan → entity present in every scene, no age line.
- Unassigned scene → existing "assign an event" copy, nothing resolved.

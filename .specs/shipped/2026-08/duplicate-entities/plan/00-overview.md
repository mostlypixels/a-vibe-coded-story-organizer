# Duplicate entities — plan overview

The manual for this feature's tasks. Never implemented, never moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `01-duplicate-name.md` | `App\Support\DuplicateName` — the `(n)` suggestion algorithm, with its unit test |
| 02 | `02-scene-duplication.md` | Route, `DuplicateEntityRequest`, `HasSiblingPosition::makeRoomAfter()`, `SceneDuplicator`, controller action, feature tests |
| 03 | `03-duplicate-dialog-and-scene-ui.md` | `x-duplicate-dialog`, the `edit-actions` trigger prop, wiring on the scenes index and edit pages |
| 04 | `04-codex-entry-duplication.md` | `CodexMediaService::copyFile()`, `CodexEntryDuplicator`, route, controller action, feature tests |
| 05 | `05-codex-entry-ui.md` | Wiring the existing dialog onto the codex index and edit pages |
| 06 | `06-documentation.md` | `documentation/architecture.md` section and any doc the feature contradicts |

02+03 is a shippable scene slice; 04+05 the codex slice. Both backends are testable before their
UI exists.

## Binding decisions

From `expanded/open-questions.md` — settled, not open for re-decision inside a task:

* **Only scenes and codex entries.** No acts, chapters, events, plotlines, or codex attribute
  definitions.
* **Duplication never walks down the manuscript tree.** "Children" means rows the entity itself
  owns.
* The name suggestion is **advisory**: `required|string|max:255` and nothing more. A submitted
  collision is accepted.
* Taken-set for the suggestion is project-wide per type, case-insensitive.
* Both actions redirect to the **copy's edit page** with `session('status') === 'duplicated'`.
* Codex media **files are copied** to fresh paths before the transaction and cleaned up in a
  `catch`; a `path === null` row copies as `path === null`.
* Aliases are copied verbatim; the resulting doubled scene references are expected.
* **Two services**, not one — they share only `DuplicateName`.
* Authorization for both routes lives in one `DuplicateEntityRequest`, via `RouteProject`.

## Invariants every task must preserve

* **Authorization walks to the owning `Project`.** Both routes go through
  `DuplicateEntityRequest::authorize()`; every new endpoint ships a non-owner 403 test.
* **`position` is gappy and non-unique.** Insert by shifting later siblings, never by renumbering
  the set. The sibling scope is whatever `siblingScopeColumn()` declares — never hard-coded.
* **`scene_codex_entry` is derived.** Never insert into it; let `SceneReferenceMatcher` rebuild it
  (`syncScene` after a scene copy, `syncProject` after a codex copy).
* **One file, one owning row.** A copied media row gets a copied file at a new path. A shared path
  makes one deletion blank the other entity's images.
* **`scenes.share_token` is unique** and is never copied.
* **Tags are re-attached, never re-created.** `Tag::count()` is unchanged by a duplicate.

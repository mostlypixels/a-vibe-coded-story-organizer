---
title: Codex Attributes On Demand — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-sheet-predicates.md` | `CodexAttributeSheets`: `attached()`, fixed `setOnly()`, `unattachedFor()`; `forEntry()` goes private. |
| 02 | `02-cleanup-migration.md` | Drop every (entry, attribute) pair whose values are all blank. Independent of 01. |
| 03 | `03-create-form-picker.md` | Saver writes only posted baselines; create form loses the fifteen boxes, gains a picker. Independent of 01/02. |
| 04 | `04-attach-detach-routes.md` | `CodexEntryAttributeController` + `AttachCodexAttributeRequest` + two routes. |
| 05 | `05-edit-form.md` | Edit form shows attached only, with detach buttons and an attach form. Depends on 01 and 04. |

02 and 03 can run at any point. 05 is last.

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`.

1. **No schema change.** The `codex_attribute_values` row carries both meanings:
   *any row exists* = attached to this entry; *any row `filled()`* = worth showing a reader.
2. **`setOnly()` currently tests the wrong thing** (a row exists, not a filled value) and
   disagrees with `CodexAsOfResolver`. Fixing it is part of this feature, not a separate bug.
3. **`forEntry()` becomes private.** After 01 and 05 nothing outside the class calls it.
4. **A posted `attribute_baselines` key always writes a row, blank or not.** Only picked
   attributes are posted, so a blank key is a deliberate pick. Create and edit therefore
   mean the same thing by "add".
5. **`''` is attached-but-blank.** Clearing a value to blank does *not* detach. Only the
   explicit Remove detaches.
6. **The detach button is the only detach path.** The baseline row has no delete button in
   Blade and gains none; `overview.md`'s claim that "removing the last period already
   detaches it" is not reachable through the UI. Do not add a second, unconfirmed path.
7. **Detach is confirmed**, with wording that names the loss: values deleted, project
   attribute kept.
8. **The picker only picks.** No inline creation of a project attribute — that is the
   fastest road back to fifteen. A link to `projects.codex-attributes.index` instead.
9. **The cleanup migration uses the query builder**, not row-value SQL, and has no `down()`.
   Pre-V1 data.
10. **`ProjectGraphImporter` is untouched.** An archive exported before this change restores
    blank rows; old archives are throwaway.
11. **The project Attributes screen, `AttributeTimeline`, `CodexAsOfResolver`,
    `CodexEntryDuplicator` and the codex policies do not change.**

## Core invariants every task must preserve

* **The leading-anchor invariant.** Every attached pair has a Start-anchored row.
  Attaching goes through `AttributeTimeline::ensureBaseline('')`; nothing writes a
  mid-timeline row without one.
* **Row presence is now load-bearing.** Any code that writes a row is deciding "attached".
  No blanket seeding of a project's attributes onto an entry, anywhere.
* **One predicate per question, both on `CodexAttributeSheets`.** No caller re-derives
  "attached" or "has something to show".
* **Cross-project guard.** Every new action checks
  `$codexAttribute->project_id === $codexEntry->project_id` (404) as
  `CodexAttributeValueController::store()` already does. Route binding is not access control.
* **Authorization walks to the owning project** — `update` on `$codexEntry->project`,
  mirrored in the Form Request. Non-owner gets 403.

---
title: "Task 05 — Chapter delete: move or cascade"
---

# Task 05 — Chapter delete: move or cascade

## Scope

The same "move or delete" choice as task 04, one level down: deleting a Chapter with
scenes offers **move its scenes to another chapter, then delete** or **delete
everything**. Reuses task 04's `delete-with-move-dialog` Blade component — this task
should not need to modify that component's markup, only pass Chapter-shaped props to
it (unless task 04's implementation reveals the component needs a small generalization,
in which case make that change here and note it in `../resolution-log.md`'s Deviations
section).

Does **not** touch Act (task 04) or Project (task 03).

## Depends on

Task 04 — specifically its new `delete-with-move-dialog.blade.php` component. Task 04's
`ActController`/`DestroyActRequest` changes are not a dependency; only the shared
frontend component is.

## Key decisions already made

Identical shape to task 04, one level down — see task 04's "Key decisions already
made" section; all apply here with Chapter/Scene substituted for Act/Chapter:

* One request (`move_children_to` on `ChapterController::destroy()`), transaction-
  wrapped.
* Explicit `position` assignment for moved scenes (`max(position in destination) + 1`,
  ascending original order) — `Scene`'s `booted()` has the same create-only hook
  limitation as `Chapter`'s.
* `DestroyChapterRequest::authorize()` mirrors `ChapterController::destroy()`'s
  existing `$this->authorize('update', $chapter->act->project)`.
* Destination must be another chapter in the same project (mirroring
  `UpdateSceneRequest`'s existing `chapter_id` project-scoping rule at
  `app/Http/Requests/UpdateSceneRequest.php:30` — reuse that same scoping query shape).

## Where to make the change

**Backend:**
* New `app/Http/Requests/DestroyChapterRequest.php`, mirroring task 04's
  `DestroyActRequest` with the scene/chapter substitution and
  `UpdateSceneRequest`'s `chapter_id` scoping rule as the validation precedent.
* `ChapterController::destroy()`: same transaction shape as `ActController::destroy()`
  in task 04 — reassign scenes (`chapter_id` + explicit `position`) if
  `move_children_to` present, then `$chapter->delete()`.
* `ChapterController::edit()`: `$chapter->loadCount('scenes')` for the dialog count.
  `ChapterController::index()` already has `withCount('scenes')` (`:26-29`).
* Destination list: chapters in the same project excluding the one being deleted,
  scoped the same way `UpdateSceneRequest`'s `chapter_id` rule already scopes.

**Frontend:**
* `chapters/edit.blade.php` and `chapters/index.blade.php`: wire the trigger the same
  way task 04 wired `acts/edit.blade.php`/`acts/index.blade.php`, reusing the shared
  `delete-with-move-dialog` component with Chapter/Scene props.

## Tests to add

New assertions in `tests/Feature/ChapterTest.php` (existing file) — the same six
scenarios task 04 added to `ActTest.php`, one level down (scenes instead of chapters,
chapters instead of acts as the destination type):

* Zero scenes → unchanged behavior.
* Scenes, no other chapter exists → informational-only dialog, cascades on submit.
* Scenes, another chapter exists, "delete everything" chosen → cascades as before.
* Scenes, "move, then delete" chosen → scenes reassigned with correct `chapter_id` and
  appended `position`, in original relative order; source chapter deleted; scenes not
  deleted.
* `move_children_to` validation: cross-project destination rejected, self-reference
  rejected, non-owner gets 403.
* Position invariant regression: no two scenes share a `position` within the
  destination chapter after a move.

Run `composer test -- --filter=ChapterTest`, then the full `composer test` + `npm run
test` + `composer lint` — this is the last task in the plan.

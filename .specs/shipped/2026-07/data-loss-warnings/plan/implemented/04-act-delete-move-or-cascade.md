---
title: "Task 04 — Act delete: move or cascade"
---

# Task 04 — Act delete: move or cascade

## Scope

Deleting an Act with chapters offers a choice: **move its chapters to another act,
then delete** (one request, one transaction), or **delete everything** (the existing
cascade, now with an honest count). No destination available (only act in the
project) → only "delete everything" shows, no dialog dead-end. Zero chapters → no
dialog at all, original unqualified `confirm()`, unchanged.

This task also creates the **reusable dialog component** (`delete-with-move-dialog`)
task 05 (Chapter) will reuse — build it generically enough that task 05 only needs to
pass different props, not duplicate markup.

Does **not** implement the Chapter-level equivalent (task 05) or touch Project's
confirmation (task 03).

## Depends on

Nothing from 01/02/03 — independent JS/backend surface. (Task 05 depends on *this*
task's new Blade component.)

## Key decisions already made

* Move + delete is **one request** — extend `ActController::destroy()` to accept an
  optional `move_children_to` field rather than adding a new route.
* **Transaction-wrapped**: reassignment then delete, atomically (`CLAUDE.md`'s
  multi-step-write rule) — a failure partway must leave neither a partial reassignment
  nor an orphaned act.
* **Position invariant, explicit**: `Chapter`'s `booted()` only assigns `position` on
  `creating`, not on update (`app/Models/Chapter.php:56-60`) — this task must set
  `position` on every reassigned chapter explicitly:
  `max(position) in destination) + 1`, assigned in ascending original-position order so
  relative order survives the move. Do not rely on the model's create-only hook.
  This is the concrete failure mode to guard against: two chapters silently sharing a
  `position` in the destination act.
* **Authorization**: `DestroyActRequest::authorize()` mirrors the controller —
  `$this->user()->can('update', $act->project)`, no new policy.
* **Destination validation**: `move_children_to` must be another act in the *same*
  project, not the act being deleted itself.

## Where to make the change

**Backend:**
* New `app/Http/Requests/DestroyActRequest.php`, following the existing `Update*Request`
  pattern (`UpdateChapterRequest`'s `act_id` rule is the closest precedent):
  ```php
  'move_children_to' => [
      'nullable',
      Rule::exists('acts', 'id')->where('project_id', $act->project->id),
      Rule::notIn([$act->id]),
  ],
  ```
* `ActController::destroy()`: accept `DestroyActRequest` instead of the bare model
  binding; inside a `DB::transaction()`, if `move_children_to` is present, reassign
  every chapter (`act_id` + explicit `position`) to the destination in ascending
  original-position order, then `$act->delete()` as before. See `architecture.md` §4b
  for the reference implementation.
* `ActController::edit()`: `$act->loadCount('chapters')` +
  `Scene::whereHas('chapter', fn ($q) => $q->where('act_id', $act->id))->count()` for
  the dialog's scene total. `ActController::index()` already has `withCount('chapters')`
  (`:22`) — nothing to add there.
* Destination list for the picker: `$act->project->acts()->where('id', '!=',
  $act->id)->orderBy('position')->get()`.

**Frontend:**
* New `resources/views/components/delete-with-move-dialog.blade.php` — see `ui.md`'s
  "Act / Chapter delete" section for the reference markup (`<x-dialog>`-based, a radio
  choice between "move" and "delete", a `<select>` shown only in move mode, collapsing
  to a single informational line + Confirm when `$destinations` is empty).
* `acts/edit.blade.php` and `acts/index.blade.php`: replace the `<x-delete-button>`/
  `<x-icon-delete-button>` trigger with one that opens this dialog (via `open-modal`)
  when the act has chapters; keep the existing plain `confirm()` trigger unchanged when
  it has none.

## Tests to add

New assertions in `tests/Feature/ActTest.php` (existing file):

* **Zero chapters**: unchanged behavior — original unqualified `confirm()`, `DELETE`
  with no `move_children_to` deletes normally. Regression guard this feature didn't
  touch the empty case.
* **Chapters, no other act exists**: edit page renders the dialog with the
  informational "delete everything" text only, no `<select>`; submitting with no
  `move_children_to` cascades as before.
* **Chapters, another act exists, "delete everything" chosen**: submitting with no
  `move_children_to` cascades exactly as before — proves the new field is additive.
* **Chapters, "move, then delete" chosen**: submitting with `move_children_to=<id>` —
  every chapter's `act_id` now points at the destination; every moved chapter's
  `position` is greater than every pre-existing chapter's position there, in original
  relative order; the source act is deleted; the moved chapters are **not** deleted.
* **`move_children_to` validation**: a destination from a different project is
  rejected; the act's own id as a destination is rejected; a non-owner submitting any
  variant gets 403.
* **Position invariant regression**: after a move, no two chapters share a `position`
  within the destination act.

Run `composer test -- --filter=ActTest` to verify in isolation, then the full
`composer test` before moving on (this task changes shared infrastructure — the new
Blade component — that task 05 depends on).

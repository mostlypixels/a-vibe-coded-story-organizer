---
title: Data Loss Warnings — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-store-dirty-tracking.md` | Add a page-wide "is anything unsaved right now" signal to `Alpine.store('autosave')`. Foundation for 02. |
| 02 | `02-navigation-guard.md` | In-app click interceptor + custom Leave/Cancel dialog + native `beforeunload` fallback, mounted globally. Depends on 01. |
| 03 | `03-project-delete-confirmation.md` | Cascade-count string for Project delete (no reassign option). Independent of 01/02/04/05. |
| 04 | `04-act-delete-move-or-cascade.md` | Reusable "move or delete" dialog component + Act's Form Request/controller/position-invariant backend. |
| 05 | `05-chapter-delete-move-or-cascade.md` | Same shape one level down for Chapter, reusing the dialog component task 04 creates. Depends on 04. |

03 can run in parallel with 01/02 if convenient — it touches an unrelated controller/view (`ProjectController`/`projects/edit.blade.php`) with no shared code. 04 and 05 are sequential only because 05 reuses 04's new Blade component; 05 does not depend on 04's Act-specific backend code.

## Binding design decisions (do not re-litigate)

All resolved via grilling — full record in `../resolution-log.md`.

1. **No Livewire in this app.** "Intercept navigation" only ever means intercepting
   `<a>` clicks and the two native browser exit paths (`beforeunload`, tab-close).
2. **`dirty` is a new signal**, distinct from the existing per-field `state` machine
   value already in `Alpine.store('autosave').fields`. A field is `dirty` from the
   first keystroke until a successful save/flush — including the ~2s debounce window
   where `state` is still `idle`. Both guard and `beforeunload` read `isDirty()`, never
   `worstState()`.
3. **The navigation guard mounts globally** in `layouts/app.blade.php`, exactly like
   the existing `<x-autosave-status-badge />` — no per-page opt-in. A page with zero
   autosave fields costs nothing (`isDirty()` returns `false`, store may not even
   exist).
4. **`autosave:explicit-leave`** (a plain `window` `CustomEvent`, no payload needed) is
   dispatched only from the in-app guard's Leave button, never from `beforeunload`
   (which cannot know the outcome). This is the entire integration surface with the
   sibling `autosave-storage-improvements` spec — no shared module, no import.
5. **V1 scope is autosave-field pages only.** The guard reads `isDirty()`; a plain
   non-autosave form left dirty is not covered. Not a bug to fix here.
6. **Project delete stays a plain cascade-count `confirm()`** — no reassign option
   (no natural "other project" destination). List only non-zero categories.
7. **Act/Chapter delete gets a real "move or delete" choice**, not just a richer
   count: a custom `<x-dialog>` (native `confirm()` can't render a `<select>`) offering
   **"Move children to another act/chapter, then delete"** or **"Delete everything"**
   (the pre-existing cascade, honest count). No destination available → only "Delete
   everything" shows, no blocking.
8. **Move + delete is one request.** The existing `destroy()` action gains an optional
   `move_children_to` field; the controller reassigns children then deletes the
   now-empty parent, wrapped in one DB transaction.
9. **Moved children are appended, not interleaved.** Each reassigned child gets
   `position = max(position in destination) + 1`, assigned in ascending original-
   position order so relative order survives the move.
10. **Zero-children delete is unchanged** — no dialog, the original unqualified
    `confirm()` text, exactly as today. The new dialog only ever appears when there's
    something to count or move.

## Core invariants every task must preserve

* **Authorization flows from the owning `Project`.** Every new/changed action
  (`DestroyActRequest`/`DestroyChapterRequest`, the reassignment logic) authorizes via
  `$this->authorize('update', $act->project)` / `$chapter->act->project` — the same
  check `destroy()` already performs, never a new policy.
* **Position uniqueness per sibling scope.** No two chapters may share a `position`
  within the same `act_id`; no two scenes within the same `chapter_id`. Task 04/05 must
  not reintroduce the collision `Chapter`/`Scene`'s create-only `booted()` hook already
  leaves latent for plain updates.
* **The FK cascade + `booted()` file-cleanup hooks are untouched.** "Delete everything"
  must still go through the exact same `$act->delete()`/`$chapter->delete()` path as
  today — these tasks only add an optional reassignment *before* that call, never
  change what deletion itself does.
* **`CLAUDE.md`'s multi-step-write transaction rule.** Reassignment + delete in task
  04/05 is one DB transaction — a failure partway must never leave a partial
  reassignment or an orphaned parent.

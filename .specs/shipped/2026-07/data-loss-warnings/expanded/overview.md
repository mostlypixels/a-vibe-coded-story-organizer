---
title: Data Loss Warnings — Overview
---

# Overview

Grounded in `spec.md`, the `autosave-with-revisions` handoff (`.specs/planned/2026-07/
autosave-with-revisions/handoff.md` §3.4/§9.7, now shipped — PR #24), this session's
reading of `resources/js/autosave/{field,store,badge}.js`, `resources/views/components/
{modal,dialog,delete-button,icon-delete-button,edit-actions}.blade.php`, and the
Act/Chapter/Project controllers and models.

## Problem

Two independent ways this app currently lets work disappear without the writer
confirming it:

1. **In-app navigation away from a dirty autosave field.** `resources/js/autosave/
   field.js` tracks a `dirty` flag per field instance and autosaves on a 2-second
   debounce, blur, or `Ctrl-S`. But `dirty` is never surfaced past the individual
   field component — `Alpine.store('autosave').fields` (registered in
   `registerAutosaveField()`) only tracks each field's **state machine value**
   (`idle`/`saving`/`saved`/…), not whether it currently has unsaved input. In the
   window between a keystroke and the debounce firing — up to 2 seconds, every time —
   a field is dirty but still reports `idle`. Clicking any in-app link during that
   window (sidebar nav, "back to project", a breadcrumb) navigates away instantly, with
   nothing to catch it.
2. **No warning of any kind exists today**, not even the browser's own generic one —
   `grep -rn beforeunload resources/js` finds nothing. The source spec's non-goals
   section assumed a native `beforeunload` prompt was already in place and only needed
   leaving alone; that assumption doesn't hold. **Correction from expansion:** wiring a
   minimal native `beforeunload` listener (same `dirty` signal, standard
   `e.preventDefault(); e.returnValue = ''` idiom) is now in scope here, not a
   pre-existing thing to leave untouched. See `open-questions.md`.
3. **Deletion cascades have no confirmation that reflects the blast radius, and no
   alternative to cascading at all.** `resources/views/components/delete-button.blade.php`
   and `icon-delete-button.blade.php` both wrap a plain `onsubmit="return confirm('{{
   $confirm }}')"`, and every call site (`acts/edit.blade.php`, `acts/index.blade.php`,
   `chapters/edit.blade.php`, `chapters/index.blade.php`, `projects/edit.blade.php`,
   `plotlines/*`, `events/*`, `scenes/*`) passes a static string like `"Are you sure you
   want to delete this act?"` — never a count of what's nested underneath, and never an
   option to keep the children. Deleting an Act cascades (DB FK, confirmed in
   `app/Models/Act.php`'s `booted()`) through every Chapter and Scene in it; deleting a
   Project cascades through everything. Plotlines, Events, and Scenes are leaves (no
   children) — their generic confirm text is already accurate and out of scope here.
   Grilling surfaced that single-record reparenting *already exists* —
   `chapters/edit.blade.php` has an `act_id` picker, `scenes/edit.blade.php` has a
   `chapter_id` picker, both validated server-side (`UpdateChapterRequest`/
   `UpdateSceneRequest`) — just not offered in bulk from the delete flow.

## Goals

* A **global in-app navigation guard**: intercept clicks on in-app `<a>` links
  site-wide (not per-page-scoped) whenever any mounted autosave field is dirty; show a
  custom confirmation dialog — **Leave** / **Cancel** — before following the link.
* A **native `beforeunload` fallback** for actual tab-close/browser-quit/hard
  navigation, using the same dirty signal, since none exists today.
* Clicking **Leave** is an explicit, informed answer, broadcast as a `window` custom
  event (`autosave:explicit-leave`) other code can listen for. This is the integration
  seam the sibling draft `autosave-storage-improvements` depends on: an explicit
  in-app "leave anyway" should suppress that feature's crash-recovery `localStorage`
  write, since the writer was asked and chose to discard. `beforeunload` can never emit
  this event (see non-goals) — that path always leaves `autosave-storage-improvements`
  to write defensively.
* **A "move or delete" choice for Act and Chapter** (the two entities whose children
  can meaningfully live somewhere else): deleting an Act/Chapter with children opens a
  custom dialog offering **"Move to another act/chapter, then delete"** (a destination
  picker; reassignment + delete happen as one action) or **"Delete everything"** (the
  existing cascade, now with an honest count). If there's no other act/chapter in the
  project to move to, only "Delete everything" is shown — no dead-end blocking.
* **Cascade-aware delete confirmation for Project** — the one entity kept to a plain
  count string, since a project's direct children (acts, plotlines, events, codex
  entries) have no natural "other project" to move to. Lists only the non-zero
  categories, e.g. "This project has 2 acts and 5 codex entries, which will also be
  deleted." Plotline/Event/Scene (leaves, no children) keep their existing generic text
  unchanged.

## Non-goals

* Replacing or scripting custom buttons into the native `beforeunload` dialog itself.
  Browsers deliberately withhold which button the user clicked from JS — this can only
  ever be a generic, unconditional "leave site?" prompt, never smarter than that.
* A general-purpose "any form on any page" dirty-tracking framework. Scope the
  navigation guard to pages using `x-autosave-field`; broadening to non-autosave forms
  (e.g. the codex-attribute-value forms) is a candidate follow-up, not v1.
* Changing the DB-level cascade-delete behavior itself (FK cascade + `booted()` file
  cleanup, already correct) — "Delete everything" still goes through the exact same
  path; this spec only adds an alternative and makes the confirmation honest.
* Deep/full nested-tree counts for the cascade confirmation (e.g. "…and 47 scenes and
  212 revisions"). Scope to immediate + one level down (Act → chapters + scenes;
  Project → the direct collections it owns).
* A "move to another project" option for Project deletion, or any cross-project
  reassignment. Project keeps the plain cascade-count confirmation only.
* General multi-select reparenting outside the delete flow (e.g. a bulk "move these 3
  chapters" tool independent of deleting anything) — this spec only builds the
  reassign-then-delete path.

## User stories

* As a writer, I click "Story overview" in the sidebar while a scene's contents field
  is mid-debounce. A dialog stops me: "You have unsaved changes — leave anyway?" I
  click Cancel, the field finishes autosaving a second later, then I navigate freely.
* As a writer, I close the browser tab by mistake while a field is dirty. The browser's
  own "Leave site? Changes may not be saved" prompt catches me before the tab actually
  closes.
* As a writer, I click Delete on an Act that has 2 chapters and 9 scenes in it, and
  another act exists in the project. The dialog offers to move those chapters into the
  other act instead of losing them, or to delete everything — instead of the old
  generic "delete this act?" that gave no hint of the blast radius or any alternative.
* As a writer, I click Delete on the *only* act in a project. The dialog has no
  destination to offer, so it just shows the honest "delete everything" count, same as
  before this feature existed.

## Acceptance criteria

* Clicking an in-app link while any `x-autosave-field` instance is dirty shows the
  custom Leave/Cancel dialog; clicking Leave navigates to the original href and
  dispatches `autosave:explicit-leave`; clicking Cancel stays on the page with no
  navigation and no event.
* A clean page (no dirty fields, or no autosave fields at all) navigates instantly,
  with zero behavior change from today.
* Closing a tab/browser or hard-navigating (typing a URL) with a dirty field triggers
  the native `beforeunload` prompt; a clean page does not.
* Deleting an Act/Chapter with children and at least one valid destination shows a
  dialog with both "Move, then delete" (picker) and "Delete everything" (honest count)
  options; with no valid destination, only "Delete everything" shows.
* Deleting a Project shows a confirmation naming the non-zero counts of what's nested
  underneath (no move option); deleting a Plotline/Event/Scene is unchanged.
* Choosing "Move, then delete" reassigns every child to the destination (appended at
  `max(position) + 1`, preserving relative order) and deletes the now-empty parent, all
  in one request.

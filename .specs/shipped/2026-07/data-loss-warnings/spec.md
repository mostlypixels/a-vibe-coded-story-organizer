---
status: shipped
shipped: 2026-07-24
planned: 2026-07-24
expanded: 2026-07-24
---

# Data Loss Warnings

## Problem

`autosave-with-revisions` autosaves dirty fields on a 2-second debounce, but nothing
today warns a writer before they navigate *away* from a dirty field via an in-app link
(sidebar nav, "back to project", breadcrumb, etc.). The only safety net for leaving a
page is the browser's native `beforeunload` prompt, which only fires on tab-close /
browser-quit / hard navigation (typing a new URL) — it does not intercept clicking a
link inside the app, and its text/buttons cannot be customized. Right now, clicking an
in-app link away from a scene/project edit form while a field is mid-debounce (or has
otherwise failed to save) loses that edit silently, with no prompt at all.

Deletion cascades (deleting an act/chapter/scene/project and everything nested under
it) have a related but separate problem: no "are you sure" confirmation before an
irreversible, cascading delete. Both are "the app let something disappear without the
writer confirming it" — grouped here as one spec, but likely two independent pieces of
work.

## Goals

* A custom confirmation modal — "You have unsaved changes — leave anyway?" with
  explicit **Leave** / **Cancel** buttons — that intercepts in-app navigation (link
  clicks, and Livewire/route-driven navigation if applicable) whenever at least one
  autosave field on the page is dirty (edited since the last successful autosave or
  `Ctrl-S` flush; see `resources/js/autosave/field.js`'s existing `dirty` state).
* Clicking **Leave** is an explicit, informed answer. That answer is a signal the
  sibling draft `autosave-storage-improvements` depends on: an explicit in-app "leave
  anyway" should suppress that feature's crash-recovery `localStorage` write, since the
  writer was asked and chose to discard — the mechanism shouldn't second-guess a
  decision it was present for.
* A confirmation step before deletion cascades (act/chapter/scene/project delete),
  naming what will be removed (e.g. "This will also delete 4 scenes").

## Non-goals

* Replacing or duplicating the native `beforeunload` prompt for actual tab-close /
  browser-quit — that path is kept as-is. Browsers deliberately don't let a page learn
  which button the user clicked on that dialog, so it can only ever be a generic,
  unconditional warning; it is not a mechanism this spec can make smarter, and
  `autosave-storage-improvements` treats "left via native beforeunload" as always
  worth a defensive draft save for exactly this reason.
* A general-purpose "any form on any page" dirty-tracking framework. Scope to pages
  using the autosave field component first; broadening to non-autosave forms is a
  candidate follow-up, not v1.

## Rough approach

* A small Alpine/JS layer that intercepts clicks on in-app `<a>` navigation (and
  reads `Alpine.store('autosave').worstState()` or an equivalent "any field dirty"
  check) — on an intercepted click with dirty state present, prevent the default
  navigation, show the modal, and only proceed to the original destination if the
  writer confirms **Leave**.
* Deletion-cascade confirmation is a separate, simpler modal on the existing
  delete actions (Act/Chapter/Scene/Project controllers), listing what's nested
  underneath before submitting the delete.

## Open questions

* Should the in-app navigation guard cover all forms eventually, or stay scoped to
  autosave-field pages for v1? (Leaning: scope tightly for v1, per non-goals above.)
* Exact wording/count format for the deletion-cascade confirmation (e.g. does it need
  to walk the full nested tree, or just the immediate children's counts?).

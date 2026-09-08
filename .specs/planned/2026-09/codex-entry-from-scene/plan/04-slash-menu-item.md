---
title: "Task 04 — Slash menu item"
---

# Task 04 — Slash menu item

## Scope

A `New codex entry` item in the editor's slash menu (`buildSlashItems()` in
`resources/js/wysiwyg.js`), which removes the `/…` range and opens the dialog task 03
built.

Does **not** build a selection popover — deferred, see `expanded/open-questions.md`.

## Depends on

Task 03.

## Key decisions already made

* One code path with the sidebar button. This item opens the same dialog; it does not get
  its own create logic.
* It deletes the typed `/…` range first, the way the other slash items do, so the command
  text never survives into the prose.
* Opening from here leaves the name empty — there is no selection. Prefill stays the
  selection's job.
* `wysiwyg.js` already dispatches a `wysiwyg:text-changed` CustomEvent; use an event to
  reach the dialog rather than importing the dialog into the editor.

Detail: `expanded/ui.md` → *Trigger*.

## Tests to add

Extend `resources/js/wysiwyg.test.js`:

* The item appears in the slash menu list.
* Choosing it removes the `/…` range.
* Choosing it asks for the dialog exactly once.

---
title: "Task 03 — Dialog and sidebar"
---

# Task 03 — Dialog and sidebar

## Scope

The writer-facing half: the dialog, the sidebar button that opens it, the list swap, and
the confirmation line.

Does **not** add the slash-menu item — task 04, which opens this same dialog.

## Depends on

Tasks 01 and 02.

## Key decisions already made

* `resources/views/scenes/partials/quick-codex-entry.blade.php`, an `x-dialog` holding: a
  name input (autofocus, prefilled from the editor selection, trimmed); the three entry
  types as radios, not a select; Create and Cancel, with Enter submitting; and an inline
  error region.
* **Not** a `<form method="POST">`, and it must sit **outside** the scene `<form>` — a
  stray Enter must never submit the scene.
* Trigger here: a `+ New codex entry` button in the *Codex references*
  `x-collapsible-card` in `scenes/edit.blade.php`. Always reachable, needs no selection.
* Flow: flush the pending `contents` autosave with `runMatcher: false` and await it, then
  POST `{ name, type }` with the page CSRF token.
* On success: swap the reference list with the HTML the endpoint returned, show the
  confirmation line naming the created entry with a link to it, close the dialog.
* On 422: render the message in the dialog, keep it open, keep the typed name. When the
  message carries an existing entry id, offer *Open :name*.
* On any other failure: keep the dialog open with one generic message.
* Focus returns to the editor at the previous selection on close — success or cancel.
* The card's line *"Detected from the scene contents on last save."* becomes wrong once the
  list updates live. Reword it to name the autosave.

Detail: `expanded/ui.md` → *Dialog*, *Alpine flow*, *Sidebar list*.

## Tests to add

A Vitest file beside the module:

* Prefill: the selection text becomes the name value, trimmed.
* Submit flushes the autosave **before** posting — assert the order; the whole feature
  turns on it.
* Success replaces the list with the returned HTML, so an entry the resync dropped
  disappears.
* Success shows the confirmation line even when the returned list does not contain the new
  entry.
* A 422 keeps the dialog open with the typed name intact.
* A network failure keeps the dialog open.

Extend `tests/Feature/SceneTest.php`: the scene edit page renders the new button and the
dialog markup outside the scene form.

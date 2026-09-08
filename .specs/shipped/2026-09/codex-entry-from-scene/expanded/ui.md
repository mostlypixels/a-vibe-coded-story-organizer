# UI

## Trigger

Two, one code path.

- **Sidebar button** — `+ New codex entry` in the *Codex references*
  `x-collapsible-card` (`resources/views/scenes/edit.blade.php:176`). Always reachable,
  needs no selection.
- **Slash menu item** — a `New codex entry` item in `buildSlashItems()`
  (`resources/js/wysiwyg.js:404`), which deletes the `/…` range and opens the same dialog.

Both open one `x-dialog`. When the editor holds a non-empty selection, the name field is
prefilled with `editor.state.doc.textBetween(from, to)`, trimmed. A selection popover is a
third trigger and is deferred (`open-questions.md`).

## Dialog

`resources/views/scenes/partials/quick-codex-entry.blade.php`, an `x-dialog` holding:

- Name — `x-text-input`, autofocus, prefilled from the selection.
- Type — three radios, not `x-select`. Three options, and a radio is one click.
- Create / Cancel. Enter submits.
- An inline error region for the duplicate refusal, rendering the message plus an
  *Open :name* link to `codex.show`.

Not a `<form method="POST">`: Alpine posts it, so a stray Enter can never submit the scene
form around it. The dialog must sit **outside** the scene `<form>` element for the same
reason.

## Alpine flow

1. Flush the pending autosave on `contents` and await it. `resources/js/autosave/field.js`
   already has the Ctrl-S flush path — reuse it, do not write a second flush. Without this,
   `syncScene()` runs against stale prose and the new name does not match.
2. POST with the page CSRF token and `{ name, type }`.
3. On 200, replace the sidebar list from `referenced_entries` and close the dialog.
4. On 422, render the error in the dialog and keep it open.
5. On any other failure, keep the dialog open with a generic message. Never close on
   error — the typed name is the thing being protected.

Focus returns to the editor at the previous selection on close, success or cancel. That is
the "back to the paragraph" goal, and it is the part most easily dropped.

## Sidebar list

The card `@foreach` becomes an Alpine-rendered list seeded from `$referencedEntries`, so
step 3 can replace it. Keep the server-rendered markup as the initial state — the sidebar
must not depend on JS to render at all.

The card line "Detected from the scene contents on last save." becomes wrong the moment
this feature updates it live. Reword it to name the autosave.

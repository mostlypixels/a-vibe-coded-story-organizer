# Overview

Remove the `localStorage` draft mirror and its page-level recovery modal. The 2-second
server autosave, the blur flush, and Ctrl-S already cover what the mirror was built for.

## Why now

The restore path is broken and fails silently. `draft-recovery.js`'s `restore()` writes the
draft into the `<textarea>` and fires `input`. That reaches the autosave state machine and
the word counter — but **not the editor**. Every field except `Project.rights` renders as
`<x-wysiwyg>`, and Tiptap reads `textarea.value` once at `init()` (`wysiwyg.js:563`); it
registers no `input` listener and nothing calls `setContent` afterwards.

Two outcomes, both bad:

| Writer's next action | Result |
|---|---|
| Nothing for ~2 s | The debounce PATCHes the *restored draft*. The editor still shows the old text. **Screen and database disagree, silently.** |
| Types in the editor within ~2 s | Tiptap's `onUpdate` → `syncTextarea()` overwrites `textarea.value` with the editor's old content. **The restored draft is gone.** |

Fixing it means adding a `wysiwyg:set-content` channel to a feature that should not exist.
Deleting the feature deletes the bug.

## The keep-argument is dead

The strongest reason to keep drafts would be offline/session-expiry replay depending on
them. It does not. `replayIfQueued()` (`field.js:415`) calls `save()`, which re-reads the
live `<textarea>` via `fieldValue()`. No `localStorage` involved. Verified, not assumed.

## What is genuinely lost

- Text typed in the ≤2 s between the last keystroke and the debounce tick, when the machine
  dies hard (power loss, OS kill) with no `beforeunload`.
- A dirty field whose session expired, where the writer then closes the tab past the native
  prompt instead of signing back in.

Both survive today only as a *prompt on the next page load*, not as an automatic recovery —
and for a wysiwyg field the prompt cannot actually put the text back.

## What survives

- Server autosave: 2000 ms debounce (`DEBOUNCE_MS`), flush on `focusout`, flush on Ctrl-S.
- The in-app unsaved-changes modal and the native `beforeunload` prompt
  (`navigation-guard.js`). Neither touches `localStorage`.
- Revisions and the compare/history UI. Untouched.

## What is gained beyond the bug fix

- No plaintext manuscript at rest in `localStorage`, on whatever machine the writer borrowed,
  with no age-based eviction.
- The false-conflict "Compare" prompt disappears — a dev `migrate:fresh --seed` reissuing the
  same autoincrement ids can no longer surface a stranger's draft. That collision class was
  `autosave-storage-improvements`' own stated problem; this closes it by removal.

## Acceptance criteria

- No code path writes to or reads from `localStorage`.
- No modal opens on page load.
- `.claude/fix-tiptap-restore.md` is deleted — the bug it describes no longer has code to live in.
- `npm run test` and `composer test` green.
- Manual: edit a scene, blur, reload — text persisted. Edit, wait 2 s, reload — text persisted.

## Non-goals

- Changing the debounce window, retry schedule, or state machine.
- Adding any replacement recovery mechanism (a `wysiwyg:set-content` channel, a shorter TTL,
  a server-side draft column). If crash recovery is wanted later it is a new spec, and it
  starts from the editor, not from the textarea.
- Touching the navigation guard's own behaviour — only the now-listenerless event it dispatches.

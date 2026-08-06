# Handoff — draft recovery does not reach a Tiptap editor

> [!WARNING]
> **Scratch. Delete when the fix lands.** Never cite this file from code or from
> `documentation/`.

Found 2026-08-06 during the comment cleanup, not by a bug report. A comment in
`draft-recovery.js` claimed Tiptap hydrates from the restore's `input` event. A cold
review disproved the claim. The claim was false because the code is broken.

**Nothing is fixed. The only change so far is a `> [!WARNING]` in `restore()`'s docblock
recording the true mechanism.**

## The mechanism

`restore()` (`resources/js/autosave/draft-recovery.js:109`) does two things:

1. writes the draft into the field's real `<textarea>`;
2. dispatches a bubbling `input` event.

That is correct for a plain `<textarea>`. `Project.rights` is the only one.

Every other field renders as `<x-wysiwyg>` (`autosave-field.blade.php:104-112` sends
every non-`Plain` kind there). Then:

| Fact | Where |
|---|---|
| Tiptap reads the textarea **once**, at `init()` | `wysiwyg.js:563` — `content: textarea.value` |
| It registers no `input` listener | only `mousedown`/`mouseenter`/`submit` exist in that file |
| Nothing calls `setContent` after mount | — |
| The textarea is hidden once mounted | `x-show="! ready"` |
| `restore()` always runs after mount | it is click-driven, `autosave-draft-recovery-modal.blade.php:28` |

So the restore reaches the autosave state and the word counter. It never reaches the
editor.

## Two bad outcomes, not one

`field.js:211` hears the `input`, marks the field dirty, and starts the 2000 ms debounce
(`DEBOUNCE_MS`, `field.js:29`). `fieldValue()` reads `textarea.value`. What happens next
depends on whether the writer touches the editor first.

- **Writer does nothing for ~2 s.** The autosave sends the *restored draft* to the
  server. The editor still shows the old text. **The screen and the database now
  disagree, silently.** This is the worse case: nothing looks wrong.
- **Writer edits the editor within ~2 s.** Tiptap's `onUpdate` calls `syncTextarea()`
  (`wysiwyg.js:527,573`), which overwrites `textarea.value` with the editor's *old*
  content. **The restored draft is gone.**

Draft *writes* are fine. `mirrorDraft()` reads the textarea, which Tiptap keeps in sync
on every update. Only the restore direction is broken.

## Why the tests pass

`resources/js/autosave/draft-recovery.test.js:19` builds a bare `<textarea>` fixture. No
Tiptap. The fixture is the one case that works.

Any fix needs a test with a mounted editor, or at least a fake that refuses to re-read
the textarea after mount.

## Fix options

1. **Give `<x-wysiwyg>` a restore channel.** A `wysiwyg:set-content` CustomEvent that
   calls `editor.commands.setContent()`. `restore()` dispatches it; the plain-textarea
   path is unchanged. Symmetric with the existing `wysiwyg:text-changed` channel, so it
   fits the arm's-length pattern the two files already use.
2. **Restore before mount.** Have the modal resolve before `wysiwyg` `init()` runs, so
   `content: textarea.value` picks the draft up for free. Removes a code path, but makes
   the dialog block first paint, and the writer would answer it before seeing the page.

Option 1 is the smaller change and keeps the decision with the writer. Do not fix it by
a second textarea read on a timer.

## The open design question

The instruction that opened this handoff was: *"Local storage save might not have been a
great idea."* Worth answering before option 1 or 2 is built — a fix to a feature that
should not exist is wasted work.

What the drafts actually buy, given the field already autosaves every 2 s and flushes on
blur and Ctrl-S:

- a browser crash or power loss inside the debounce window — at most ~2 s of typing;
- a tab close, already covered by `snapshotDraftIfDirty()` on `beforeunload`;
- a save that fails while offline — but `replayIfQueued()` (`field.js:208`) is a separate
  mechanism, and **I did not verify whether it depends on the drafts.** Check this first;
  it is the strongest argument for keeping them.

Costs on the other side: unsaved manuscript text sits in plaintext in `localStorage` with
no age-based eviction (deliberate — see `writeDraft()`'s docblock), on whatever machine
the writer used. Storage is bounded by evicting the oldest draft on
`QuotaExceededError`, not by age.

If the answer is "drop it", the removal is larger than the fix: the modal component, the
store keys, `draft-recovery.js`, and its tests.

## Verification

`npm run test` for the JS. `composer test` is unaffected — this is browser-side only.
Drive the real flow with the `run-imagoldfish` skill: edit a scene, kill the tab inside
the debounce window, reopen, click **Restore**, and confirm the editor text changes.

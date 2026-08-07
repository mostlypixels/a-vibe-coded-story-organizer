# 04 — Repair the surroundings and close out

**Depends on:** 02 (the event's last listener must be gone first).

## Scope

`resources/js/navigation-guard.js` — remove both `autosave:explicit-leave` dispatches
(`confirmLeave()`, and the guarded-save `submit` handler) and the comment sentences that explain
them.

> [!WARNING]
> Keep `savingViaForm = true` in that same `submit` handler. It is a local flag that suppresses
> the native `beforeunload` prompt during a Save, unrelated to drafts. Remove it and every Save
> raises "leave site?".

Two docblocks cite the deleted event as the precedent for the arm's-length CustomEvent pattern.
Repoint each at a channel that still exists (`wysiwyg:text-changed` or `word-count:reconcile`):

- `resources/js/wysiwyg.js:543`
- `resources/js/word-count.js:6`

Delete `.claude/fix-tiptap-restore.md`. Its own header says to delete it when the fix lands;
this removal is the fix.

`CHANGELOG.md` — one dated section for the PR.

## Not in this task

- `documentation/architecture.md` and `documentation/revisions.md` need no edit. Both describe
  the autosave state machine and revisions, never the draft mirror. Verify by grep rather than
  assume, but expect no change.
- `CHANGELOG.md`'s existing entries about the mirror are history. Never edit them.

## Tests

No new automated test. `npm run test` and `composer test` green.

Manual pass with the `run-imagoldfish` skill — the suite cannot see the Tiptap boundary that
caused the original bug, because the deleted fixture used a bare `<textarea>`. Full steps in
`expanded/testing.md` → *Manual verification*. The two that matter most:

- Press Save on an edit page: **no** native "leave site?" prompt. Proves `savingViaForm` survived.
- Click an in-app link with a dirty field: the unsaved-changes modal still appears and *Leave*
  still navigates. Proves the dispatch removal broke nothing.

# 02 — Strip the draft mirror from `field.js` and `store.js`

**Depends on:** 01 (nothing may still import these).

Detail: `expanded/architecture.md` → *`resources/js/autosave/field.js`* and *`store.js`*.

## Scope — `resources/js/autosave/field.js`

Delete:

- `explicitLeavePending`, its module-level `autosave:explicit-leave` listener, and
  `explicitLeaveRequested()`.
- `readDraft`, `writeDraft`, `clearDraft`, `isQuotaExceeded`, `isAutosaveDraftKey`,
  `evictOldestDraft`.
- `mirrorDraft()`, `snapshotDraftIfDirty()`, and the `_onBeforeUnload` add/remove pair in
  `init()`/`destroy()`.
- The `clearDraft(this.key)` call in `save()`'s `settled` branch.
- `onKeydown`'s `if (!config.id) { this.mirrorDraft(); return; }` early return — `flush()` is
  already id-gated by `shouldAutosave()`, so falling straight through is correct.

Rename `storageKeyFor` → `fieldKeyFor` and delete its `new:entity:parentId:field` branch
(`autosave-field.blade.php` always emits an integer `id`, so it has no caller).

Rewrite the docblocks that now describe something that no longer happens: the file docblock
("talks to `localStorage`"), `fieldKeyFor`'s, `shouldAutosave`'s, and `onInput`'s — the last
three all promise a mirror for create forms.

## Scope — `resources/js/autosave/store.js`

Delete `DRAFT_TTL_MS`, `isDraftExpired()`, `triageDraft()`, and the `> [!WARNING]` attached to
the last. Update the file docblock: it advertises "what to do with a stray `localStorage`
draft" as a decision it owns.

## Not in this task

- `compareUrls` in the store initializer — task 03, which owns the whole `compareUrl` chain.
- `navigation-guard.js`'s two dispatches of `autosave:explicit-leave` — task 04. Between this
  task and 04 the event fires with no listener. Harmless; leave it.

## Key decisions

- `shouldAutosave()`'s `id` guard stays. It is what makes the `onKeydown` deletion safe.
- Keep `this.key` and every store map it feeds — see `00-overview.md`'s invariant.

## Tests

Delete from `field.test.js`: the `draft mirror (readDraft/writeDraft/clearDraft)` describe, and
the `beforeunload` snapshot describe including its explicit-leave case. Rename the
`storageKeyFor` tests to `fieldKeyFor` and drop the `new:`-prefix case.

Delete from `store.test.js`: the `triageDraft` and `isDraftExpired` describes, plus
`DRAFT_TTL_MS` / `isDraftExpired` / `triageDraft` from the import.

Add two guards to `field.test.js`:

- a dirty field that receives `beforeunload` leaves `window.localStorage.length === 0`;
- a successful `save()` on a dirty field leaves `window.localStorage.length === 0`.

> [!WARNING]
> Assert on `window.localStorage` directly. A spy on `writeDraft` has nothing left to attach to
> and would pass vacuously.

# Architecture — the removal map

Purely subtractive. No new module, no new route, no migration, no policy change. The server
side (`FieldAutosaveController`, `AutosavableFields`, revisions) is untouched.

## Files deleted

| File | Note |
|---|---|
| `resources/js/autosave/draft-recovery.js` | the whole module |
| `resources/js/autosave/draft-recovery.test.js` | |
| `resources/views/components/autosave-draft-recovery-modal.blade.php` | |
| `.claude/fix-tiptap-restore.md` | its own header says delete when the fix lands; removal *is* the fix |

## `resources/js/autosave/field.js`

Delete:

- `explicitLeavePending`, its module-level `autosave:explicit-leave` listener, and
  `explicitLeaveRequested()` — `snapshotDraftIfDirty()` was the only reader.
- `readDraft`, `writeDraft`, `clearDraft`, `isQuotaExceeded`, `isAutosaveDraftKey`,
  `evictOldestDraft`.
- `mirrorDraft()`, `snapshotDraftIfDirty()`, and the `_onBeforeUnload` add/remove listener
  pair in `init()`/`destroy()`.
- `clearDraft(this.key)` in `save()`'s `settled` branch.
- `onKeydown`'s `if (!config.id) { this.mirrorDraft(); return; }` early return. `flush()` is
  already id-gated by `shouldAutosave()`, so falling straight through is correct.
- `compareUrls` from the store initializer, plus its write in `init()` and `delete` in
  `destroy()`. The recovery modal was its only consumer.

Rename `storageKeyFor` → `fieldKeyFor`. It no longer names a storage slot; it is the identity
key for the store's `fields`/`elements`/`dirty` maps. Its docblock must stop saying
`localStorage`. Drop the `new:entity:parentId:field` branch with it — `autosave-field.blade.php`
always emits an integer `id`, so that branch has no caller and existed only for a create-form
mirror that is going away.

> [!WARNING]
> `this.key` stays load-bearing. It keys three live store maps that the global badge and the
> navigation guard read. Deleting the key builder along with the storage functions breaks both.

Keep `shouldAutosave()`'s `id` guard as-is — cheap, and it is the reason the delete above is safe.

## `resources/js/autosave/store.js`

Delete `DRAFT_TTL_MS`, `isDraftExpired()`, `triageDraft()` and the `> [!WARNING]` attached to
the last one. Everything above them (`STATES`, `worstState`, `mapResponse`, `retryDelayMs`,
`scheduleRetry`) stays. Update the file docblock: it currently advertises "what to do with a
stray `localStorage` draft" as one of the decisions it owns.

## `resources/js/app.js`

Remove the `registerDraftRecoveryModal` import and its call.

## `resources/js/navigation-guard.js`

The `autosave:explicit-leave` CustomEvent loses its only listener. Remove both dispatch sites
(`confirmLeave()`, and the guarded-save `submit` handler) and the comments explaining them.

> [!WARNING]
> Keep the `savingViaForm = true` assignment in that same submit handler. It is a local flag
> that suppresses the native `beforeunload` prompt during a Save — unrelated to drafts, and
> removing it makes every Save raise "leave site?".

## `resources/views/components/autosave-field.blade.php`

- Drop `$compareUrl` and the `compareUrl:` entry from the `x-data` config.
- Drop `data-hash="{{ $hash }}"` from **both** the `<x-textarea>` and `<x-wysiwyg>` branches.
  `draft-recovery.js:56` was its only runtime reader; the PATCH uses `config.baseHash`, which
  is the same `$hash` value passed a different way. Keep the `$hash` variable — `baseHash`
  still needs it.
- `$historyUrl` and the History icon stay. The `revisions.compare` *route* stays; only this
  component's precomputed link to it goes.

## `resources/views/layouts/app.blade.php`

Remove the `<x-autosave-draft-recovery-modal />` mount (line ~113). Nothing replaces it — the
unsaved-changes-guard mount beside it is a different component and stays.

## Comment references to repair

Two docblocks cite `autosave:explicit-leave` as the precedent for the arm's-length CustomEvent
pattern. Point them at a channel that still exists (`wysiwyg:text-changed`, or
`word-count:reconcile`) rather than leaving a dangling name:

- `resources/js/wysiwyg.js:543`
- `resources/js/word-count.js:6`

## UI impact

None visible except the absence of a modal that should not have been shown. No new component,
no layout change, no string to translate. The per-field indicator, the global badge, the word
counter and the History icon all render exactly as before.

## Documentation

`documentation/architecture.md` and `documentation/revisions.md` describe the autosave state
machine and revisions, never the draft mirror — verified by grep, no edits needed. `CHANGELOG.md`
gets one dated section; its existing historical entries for the mirror are history and stay.

# No localstorage with autosave — plan overview

Purely subtractive. No migration, no route, no policy, no new component. The server side
(`FieldAutosaveController`, `AutosavableFields`, revisions) is not touched by any task.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `01-delete-recovery-modal-surface.md` | Delete the modal and its module, so nothing imports the draft functions |
| 02 | `02-strip-draft-mirror.md` | Remove every `localStorage` read/write from `field.js` and `store.js` |
| 03 | `03-remove-dead-field-config.md` | Drop `data-hash` and `compareUrl`, whose last reader was the modal |
| 04 | `04-repair-surroundings.md` | Remove the listenerless event, fix stale comment references, close out |

01 runs first for a mechanical reason: once no module imports `readDraft`/`clearDraft`/
`isDraftExpired`/`triageDraft`, task 02 can delete them without breaking a caller mid-task.

## Binding decisions

Settled in the grill. No task re-litigates these.

- **Pure deletion.** Nothing replaces crash recovery. If it returns it is a new spec, and it
  must hydrate the editor (`editor.commands.setContent()`), never the textarea.
- **The unsaved-changes warnings stay and need no work.** The in-app modal and the native
  `beforeunload` prompt both live in `navigation-guard.js`, both read
  `Alpine.store('autosave').isDirty()`, and neither touches `localStorage`.
- **No sweep of existing `localStorage` keys.** Pre-V1; clear by hand.
- **All three orphans go**: the `data-hash` attribute, `compareUrls`/`compareUrl`, and the
  `autosave:explicit-leave` event's two dispatch sites.
- **`storageKeyFor` → `fieldKeyFor`**, and its `new:entity:parentId:field` branch is deleted.
- **Three negative guards stay in the suite** (two JS, one PHP) — see 02 and 01.

## Invariants every task preserves

> [!WARNING]
> `this.key` is load-bearing after the rename. It keys the store's `fields`, `elements` and
> `dirty` maps, which the global badge and the navigation guard both read. Only the *storage*
> meaning of the key disappears.

- **`savingViaForm` survives** in `navigation-guard.js`'s `submit` handler. It suppresses the
  native prompt during a Save. Remove it and every Save raises "leave site?".
- **`$hash` survives** in `autosave-field.blade.php`. Only the rendered `data-hash` attribute
  goes; `baseHash` in the `x-data` config still needs the value, and the PATCH still sends it.
- **The autosave state machine is untouched**: `DEBOUNCE_MS`, `mapResponse`, `retryDelayMs`,
  `scheduleRetry`, `worstState`, `STATES`, the retry schedule, the PATCH contract.
- **`replayIfQueued()` keeps working** and never gains a storage dependency — it re-reads the
  live `<textarea>` through `fieldValue()`.
- **Nothing writes to `localStorage`.** That is the acceptance criterion, and the guards in 02
  assert it directly rather than through a spy.

## Verification

Every task ends with `npm run test` and `composer test` green. Task 04 adds the manual pass
through `run-imagoldfish` — the suite cannot see the Tiptap boundary that caused the original
bug, because the deleted fixture used a bare `<textarea>`.

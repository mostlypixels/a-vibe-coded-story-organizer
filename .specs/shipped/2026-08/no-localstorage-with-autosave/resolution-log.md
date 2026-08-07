# No localstorage with autosave — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Pure deletion.** Nothing replaces crash recovery. A future recovery feature is a new spec
  and must hydrate the editor (`editor.commands.setContent()`), never the textarea.
- **The unsaved-changes warnings stay** — the in-app modal and the native `beforeunload` prompt.
  Both already exist in `navigation-guard.js`, both read
  `Alpine.store('autosave').isDirty()`, and neither touches `localStorage`. Zero new code.
- **No sweep of `localStorage` keys already written.** Pre-V1, one browser; clear by hand. A
  sweep would keep `isAutosaveDraftKey()` alive purely to delete itself, and that cleanup code
  then never gets removed.
- **All three orphans go**: the `data-hash` attribute, the `compareUrls`/`compareUrl` chain, and
  the two `autosave:explicit-leave` dispatch sites. A dispatched event with no listener reads
  like an integration point and invites someone to wire into it.
- **`storageKeyFor` → `fieldKeyFor`**, `new:entity:parentId:field` branch deleted — no caller.
- **Three negative guards stay in the suite**, two JS and one PHP, as regression guards against
  the feature creeping back.
- **Closed by reading the code, not by asking**: `focusout` bubbles out of Tiptap (the mount
  point sits inside the component root), so the blur flush genuinely reaches the field root;
  and `replayIfQueued()` never depended on drafts — it re-reads the live `<textarea>`.

## Deviations from the spec/plan

- **One extra `field.test.js` deletion in task 02.** `destroy() removes the beforeunload
  listener too` sat in the *store dirty tracking* describe, not in the `beforeunload` describe
  the task named, so the task's delete list missed it. It asserts on `field._onBeforeUnload`,
  which the same task deletes — it had to go with it.

## Issues → resolutions

_None yet._

# Open questions

- **Sweep the `localStorage` keys already written in real browsers?** After removal nothing
  reads them, so they linger invisibly forever. *Recommend no.* Pre-V1, the only affected
  browser is the developer's own; a one-time sweep would have to keep `isAutosaveDraftKey()`
  alive purely to delete itself, and would need its own removal spec later. Clear it by hand
  once.

- **Rename `storageKeyFor` → `fieldKeyFor`?** *Recommend yes.* It stops naming a storage slot
  and becomes the store's field identity. Leaving the old name is the kind of stale fact that
  goes wrong in silence.

- **Drop the `new:entity:parentId:field` key branch?** *Recommend yes.* It has no caller —
  `autosave-field.blade.php` always emits an integer `id` — and it existed to key a create-form
  mirror that is going away. If create-form autosave is ever wanted it needs a real endpoint,
  not a key format.

- **Remove `compareUrls` from the store and `compareUrl` from the Blade config?** *Recommend
  yes.* The modal was the sole consumer. The `revisions.compare` route and the rest of the
  compare UI are unaffected.

- **Remove `data-hash` from the rendered field?** *Recommend yes.* `draft-recovery.js` was its
  only runtime reader; the PATCH's `base_hash` comes from the `x-data` `baseHash` prop, the same
  value by a different path. Counter-argument, and the reason this is a question: it is a
  cheap, correct debugging affordance and a plausible hook for a future feature. Removing it is
  the honest choice — dead attributes rot.

- **Remove the `autosave:explicit-leave` dispatches from `navigation-guard.js`?** *Recommend
  yes* — zero listeners once `field.js` stops caring. A dispatched event nobody hears is worse
  than no event: it reads like an integration point. Keep `savingViaForm`; it is a local flag,
  not a draft concern.

- **Should anything replace crash recovery?** *Recommend no, not in this spec.* If it comes
  back it must hydrate the **editor**, not the textarea — a `wysiwyg:set-content` channel
  calling `editor.commands.setContent()`, symmetric with the existing `wysiwyg:text-changed`.
  That is a separate spec with its own test that mounts a real editor. Naming it here so the
  design is not lost, not scheduling it.

- **Does `focusout` reliably flush from inside Tiptap?** `focusout` bubbles (unlike `blur`) and
  the contenteditable sits inside the field root, so it should. *This is the flush the whole
  removal leans on* — verify it in the browser before the branch merges rather than reasoning
  about it. Listed as manual step 2 in `testing.md`.

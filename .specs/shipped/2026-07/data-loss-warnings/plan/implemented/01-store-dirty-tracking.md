---
title: "Task 01 — Store dirty tracking"
---

# Task 01 — Store dirty tracking

## Scope

Add a page-wide "is anything unsaved right now" signal to `Alpine.store('autosave')`,
distinct from the state-machine value it already tracks. This is the one signal both
the navigation guard (task 02) and its `beforeunload` fallback will read.

Does **not** build the navigation guard itself, the dialog, or the `beforeunload`
listener — those are task 02.

## Depends on

Nothing — first task in the sequence.

## Key decisions already made

* `dirty` is a **new** per-field boolean map on the store (`store.dirty[key]`),
  separate from the existing `store.fields[key]` (the `STATES` enum value). A field can
  be `dirty: true` while its `state` is still `idle` — specifically during the ~2s
  debounce window between a keystroke and the autosave PATCH firing, which is exactly
  the window this feature exists to protect.
* `isDirty()` is a method on the store object (`Object.values(this.dirty).some(Boolean)`),
  not a standalone exported function — callers (task 02) will call
  `Alpine.store('autosave')?.isDirty()`, tolerating "store doesn't exist yet" (a page
  with zero autosave fields never registers it, per the existing `if (!Alpine.store
  ('autosave'))` guard in `registerAutosaveField()`) as `false`.

## Where to make the change

`resources/js/autosave/field.js`'s `registerAutosaveField()`:

* Store init block (currently `fields: {}, elements: {}`): add `dirty: {}` and an
  `isDirty()` method, alongside the existing `worstState()` method.
* `onInput()`: alongside the existing `this.dirty = true; this.mirrorDraft();`, add
  `Alpine.store('autosave').dirty[this.key] = true`.
* `save()`'s `STATES.SAVED` branch: alongside the existing `this.dirty = false;`, add
  `Alpine.store('autosave').dirty[this.key] = false`.
* `destroy()`: alongside the existing `delete store.fields[this.key]; delete
  store.elements[this.key];`, add `delete store.dirty[this.key]`.

Consult `resources/js/autosave/field.js` (already read this session — `init()`,
`onInput()`, `save()`, `destroy()`) and `architecture.md` §1 for the exact shape.

## Tests to add

Extend `resources/js/autosave/field.test.js` (existing file — this codebase's
convention is testing `registerAutosaveField()` against a mocked Alpine instance, not a
new file per tiny addition):

* Typing in a field (before the debounce timer fires) sets
  `Alpine.store('autosave').dirty[key]` to `true`.
* A successful save (mock the PATCH resolving 200) clears it back to `false`.
* `destroy()` removes the key from `dirty` entirely, mirroring the existing assertion
  that it removes the key from `fields`.
* `isDirty()` returns `true` when any registered field is dirty, `false` when none are,
  and does not throw when called before any field has registered (empty `dirty: {}`).

Run `npm run test` to verify in isolation — this task touches no PHP.

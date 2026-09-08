---
title: "Task 02 — Awaitable autosave flush"
---

# Task 02 — Awaitable autosave flush

## Scope

`resources/js/autosave/field.js` only: make a pending save awaitable, and reachable by key
from outside the field component.

Does **not** use it — task 03 is the caller.

## Depends on

Nothing.

## Key decisions already made

* `flush(options)` returns `this.save(options)` instead of dropping it, so a caller can
  await the save it just forced. It still returns early when the field is not dirty; that
  path must resolve, not return `undefined`.
* `Alpine.store('autosave')` gains `flush(key)`, returning the promise for that field's save
  (and a resolved promise when the key is unknown). The store already owns `fields`,
  `dirty` and `elements`; this is the matching verb, and task 03 is the second caller that
  earns it.
* Do not reach into a field through `elements[key]` from outside. That is what this task
  exists to avoid.
* Ctrl-S keeps `runMatcher: true`. The new seam passes whatever its caller asks for.

Detail: `expanded/ui.md` → *Alpine flow* step 1.

## Tests to add

Extend `resources/js/autosave/field.test.js`:

* `flush()` resolves once the PATCH resolves, and resolves (not `undefined`) when the field
  is clean.
* `store.flush(key)` saves that field and resolves; an unknown key resolves without error.
* `store.flush(key, { runMatcher: false })` posts `run_matcher: false`.

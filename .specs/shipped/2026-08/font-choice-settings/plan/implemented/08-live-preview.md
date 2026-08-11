---
title: "Task 08 — Live preview"
---

# Task 08 — Live preview

## Scope

An Alpine component on the appearance form that repaints the page as radios change, by
writing the font custom properties onto `document.documentElement`.

Progressive enhancement only: task 07's form must keep working with JS disabled, and
nothing here changes what is persisted.

## Depends on

07.

## Key decisions already made

* **Supersedes `expanded/ui.md` and open question 3**, which recommended
  apply-on-submit. The trade-off was accepted knowingly: judging a typeface needs it at
  full page scale, and the safety cost is bounded by the lookup-map rule below.
* **Server-rendered lookup map.** Blade renders the config lists as JSON into the
  component's `x-data`. The handler resolves `map[<list>][radio.value]` and **writes
  nothing when the key is absent** — the same "index an authored array, or fall back"
  rule as the PHP side, in JS. Never read a CSS value out of a `data-*` attribute, never
  assemble one from input: that is the mechanism the security section exists to prevent.
* The properties written are exactly the ones `FontStyleBlock` emits — `--font-sans`,
  `--font-manuscript`, `--manuscript-leading`, `--manuscript-scale`, and root
  `font-size`. Keep that list in one place; two lists will drift.
* Preview state is **not** persisted and there is no dirty-state warning: navigating away
  without saving discards it, and the next page load paints the saved values. Self-
  healing, so no extra affordance.
* New module under `resources/js/`, registered like the app's other Alpine components,
  with its resolve function exported pure so it is testable without a DOM.

## Consult

* `expanded/ui.md` → *Keyboard & a11y* (the preview must not break arrow-key nav)
* An existing `resources/js/` Alpine component for the registration pattern

## Tests to add

Co-located `resources/js/<name>.test.js` (vitest):

* A known slug resolves to the authored value from the map.
* An **unknown slug is a no-op** — no property written, nothing thrown.
* Each of the five fields writes its own property and only that one.

Then drive it in a browser via the `run-imagoldfish` skill: pick each family, confirm the
page repaints and the toolbar stays in the UI font, then save and reload to confirm the
persisted state matches what the preview showed.

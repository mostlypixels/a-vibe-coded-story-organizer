# Write Panel Refresh — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- Grill, 2026-10-22: one refresh icon per section; refresh also while the right panel is collapsed; no retry after a dropped response; no minimum interval between focus refreshes.
- Deleted cards need no code: `editor_cards` foreign keys cascade on delete, and notes and codex entries have no soft delete.
- The cards hold no Alpine, so `isEqualNode()` is a safe comparison.

## Deviations from the spec/plan

- Refresh icon: a `<summary>` must stay a direct child of `<details>`, so no flex row can wrap it. The icon sits in an absolute box inside `<details>` (end of the header line); the `<summary>` gets `pe-8` instead of `flex-1`.

## Issues → resolutions

- The browser fires `toggle` for a `<details>` that loads with `open`. A plain "refresh on toggle open" would make one GET per section at page load. `onDetailsToggle()` refreshes only on a closed → open change (`wasOpen`).
- `save()` now returns its promise for `pendingSaves`. A `.then/.catch` chain added microtask ticks and broke a test that awaits `toggle()`; an async IIFE keeps the old timing.
- Adding `x-ref` before `x-on:toggle` on `<details>` broke an `EditorTest` regex that pins attribute order. `x-ref` goes after it.

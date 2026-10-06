---
title: "Task 05 — Switcher JavaScript"
---

# Task 05 — Switcher JavaScript

## Scope

Two Alpine pieces in `resources/js/appearance-switcher.js`, registered in `resources/js/app.js`:

- **Loader** on the dropdown wrapper (task 06 puts it there): `load()` fetches
  `admin.appearance.switcher` once with `axios.get`, writes the HTML into the panel, sets
  `loaded`. A failed fetch shows the failure text and allows a retry on the next open.
- **`appearanceSwitcher(config)`** inside the partial:
  - `change(field, slug)`: apply the preview, update `active`, queue the save.
  - `step(field, direction)`: neighbour slug in `config.steps[field]`, clamped; then `change()`.
  - `canStep(field, direction)` for `:disabled`.
  - Save queue and failure handling.

Pure helpers exported for tests: `neighbourSlug(steps, current, direction)` and the queue.

Does **not** touch the nav. Task 06 owns it.

## Depends on

03, 04.

## Key decisions already made

- Preview through `resolvePreview()` from `font-preview.js`. Same property writes as
  `fontPreview.apply()`: `document.documentElement.style.setProperty`. Extract that loop into an
  exported `applyDeclarations()` in `font-preview.js` so both callers share it.
- `PATCH` body is one field only: `{ [field]: slug }`, `Accept: application/json`.
- Queue: one request in flight. A newer value for the same field replaces the queued one. No timer.
- On failure: re-apply the last saved slug for that field, set the error text from
  `config.messages.failed`. Clear the text on the next successful save.
- Unknown field or slug: no preview, no request.
- Escape, outside click and one-open-at-a-time come from `dropdown.js`. Do not re-implement them.

Detail: `expanded/architecture.md` → *JavaScript*.

## Tests

`resources/js/appearance-switcher.test.js` (Vitest):

- `neighbourSlug()` returns the neighbour and clamps at both ends; an unknown current slug
  returns null.
- Unknown slug: nothing applied, nothing sent.
- Two quick changes to one field send in order; a third queued change replaces the second.
- Changes to two fields both send.
- Failure re-applies the last saved slug and sets the error text.
- `load()` fetches once; a second open makes no request; a failed load retries on the next open.

`font-preview.test.js` stays green after the `applyDeclarations()` extraction.

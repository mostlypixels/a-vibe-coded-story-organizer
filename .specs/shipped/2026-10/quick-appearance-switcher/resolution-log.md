# Quick appearance switcher — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- 2026-10-06 grill: line spacing joins the panel. Each font gets a spacing −/+ row beside its
  size row. The source spec named size only.
- 2026-10-06 grill: no button on `admin.appearance.edit`. A switcher change there makes the form
  stale, and its Save puts the old value back.
- 2026-10-06 grill: button at every width, beside the hamburger on phones.
- 2026-10-06 grill: font options in their own face (best effort); no accessible-font marks; no
  reset button; route `admin.appearance.switcher`; save queue with no timer; load on first open.

- Task 04 panel markup contract for `appearanceSwitcher(config)` (task 05 must expose these on the
  component): `active` (field => slug), `labels` (field => slug => text), `error` (string),
  `change(field, slug)`, `step(field, dir)`, `canStep(field, dir)`. The partial reads
  `labels.<field>[active.<field>]` and binds swatch radios and selects to `change()` on `change`.
- Task 05 loader contract for task 06: `x-data="appearanceSwitcherLoader({ url, id, messages:
  { loading, failed } })"` on an element inside the dropdown panel (not on the wrapper: that
  element already holds `x-data="dropdown(...)"`). `id` is the dropdown's disclosure id; the
  loader listens for `dropdown-opened` with that id and calls `load()`. It exposes `loaded` and
  `status` (loading or failure text) and writes the HTML into `x-ref="body"`. Show the status
  line with `x-show="!loaded" x-text="status"`.

## Deviations from the spec/plan

- Task 05: a failed save also drops a newer queued value for that field, and puts the swatch
  radio or font `<select>` back on the saved slug. Without this, the control and the screen
  showed a value the server never stored.
- Task 06: one `x-navigation.appearance-switcher` instance serves every width, in a wrapper beside the account menu and hamburger. The plan named a desktop and a mobile copy. One copy avoids a second panel fetch and duplicate markup.
- Task 06: the loader needs the dropdown id, and slot content cannot read it. A nested `x-navigation.appearance-switcher-panel` reads it through `@aware`.

## Issues → resolutions

- A change made just before a link click could be lost: the browser can cancel the axios `PATCH`
  on unload. → On `pagehide`, `sendOnLeave()` sends every unconfirmed field in one
  `fetch(..., { keepalive: true })` with the `X-XSRF-TOKEN` cookie value. A duplicate of an
  in-flight save is harmless. Checked in Playwright with axios blocked: the value saved.
- Playwright check trap: the driver closes the browser right after the last command, and that
  kills a pending `keepalive` request. Keep the page open a few seconds after the navigation.

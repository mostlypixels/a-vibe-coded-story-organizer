---
title: "Task 02 — Navigation guard"
---

# Task 02 — Navigation guard

## Scope

Intercept in-app `<a>` navigation while any autosave field is dirty, with a custom
Leave/Cancel dialog; add a native `beforeunload` fallback for tab-close/hard
navigation; dispatch `autosave:explicit-leave` when the writer explicitly confirms
leaving via the custom dialog. Mounted globally.

Does **not** touch delete confirmations (tasks 03–05) — unrelated surface, no shared
code beyond both reading `Alpine.store('autosave')`.

## Depends on

Task 01 (`Alpine.store('autosave').dirty`/`isDirty()` must exist first).

## Key decisions already made

* No Livewire — plain `<a>` click interception + the two native browser exit paths.
  Nothing SPA-router-shaped to account for.
* Mount **globally** in `layouts/app.blade.php`, next to the existing
  `<x-autosave-status-badge />` — no per-page opt-in.
* `autosave:explicit-leave` is a plain `window.dispatchEvent(new CustomEvent(...))`
  with no payload — fired only from the custom dialog's Leave button, **never** from
  `beforeunload` (which cannot know which button the user picked in the native prompt —
  a hard browser restriction, not a gap to work around).
* V1 scope: only pages with `x-autosave-field` instances are covered (a page with no
  autosave fields never has `isDirty() === true`, so the guard is a correctly-behaving
  no-op there — no separate "skip this page" logic needed).

## What to build

**`resources/js/navigation-guard.js`** (new file, same shape as `resources/js/autosave/
badge.js` — a pure/testable predicate plus an `Alpine.data()` wrapper for the impure
DOM half):

* `shouldIntercept(event, anchor)` — pure predicate. Returns `false` for: no anchor,
  `event.defaultPrevented`, non-left-click (`event.button !== 0`), any modifier key
  (ctrl/cmd/shift/alt — "open in new tab" must never be blocked), `target !== '_self'`,
  a `download` attribute, cross-origin `href`, or a same-page hash-only link. Returns
  `true` otherwise. See `architecture.md` §2 for the reference implementation.
* `Alpine.data('navigationGuard', ...)` — registered once from `resources/js/app.js`
  alongside the existing `registerAutosaveField(Alpine)` call. Owns:
  * `document.addEventListener('click', handler, true)` (capturing phase, so it runs
    before other click handlers that might themselves navigate).
  * On an intercepted click where `Alpine.store('autosave')?.isDirty()` is true:
    `event.preventDefault()`, stash `anchor.href` as `pendingHref`, `$dispatch
    ('open-modal', 'unsaved-changes-guard')`.
  * `confirmLeave()`: dispatch `autosave:explicit-leave` on `window`, then
    `window.location.href = this.pendingHref`.
  * Cancel/Esc/backdrop-click: `<x-modal>`'s existing `close` handling already covers
    this — no extra code needed beyond not calling `confirmLeave()`.
* `beforeunload` listener, same file: if `Alpine.store('autosave')?.isDirty()`,
  `event.preventDefault(); event.returnValue = '';`. No custom text (browsers ignore
  it), no attempt to read which button the user picks.

**`resources/views/layouts/app.blade.php`**: add the dialog markup next to
`<x-autosave-status-badge />` (`ui.md`'s "Navigation-guard dialog" section has the
`<x-dialog>` template to adapt — reuses the existing `dialog.blade.php`/`modal.blade.php`
components, no new modal primitive).

Consult `architecture.md` §2–3 and `ui.md`'s "Navigation-guard dialog" section for the
full design.

## Tests to add

**`resources/js/navigation-guard.test.js`** (new, vitest, DOM-free — matches this
codebase's pure-logic-vs-manual-checklist split, see `badge.test.js`'s own docblock):

* Plain left-click, same-origin, no modifiers, `target` unset → `true`.
* Each of: middle/right click, each modifier key individually, `target="_blank"`,
  `download` attribute, cross-origin href, same-page hash link, `anchor === null`,
  `event.defaultPrevented` already true → `false` for each.

**Manual checklist** (documented in `testing.md`, run once before shipping — no
automated coverage for real click/`beforeunload`/`window.location`, same boundary
`field.js`'s own retry-timing/tab-focus-replay behavior already lives under):

* Dirty field, click a sidebar link mid-debounce → dialog appears; Cancel keeps the
  page, field still autosaves normally afterward; Leave navigates.
* Clean page → any in-app link navigates instantly, no dialog.
* Ctrl/Cmd-click or a `target="_blank"` link on a dirty page → opens in a new tab, no
  dialog.
* Dirty field, close the tab → native `beforeunload` prompt appears; clean field, close
  the tab → no prompt.

Run `npm run test` for the automated half; do the manual checklist against
`php artisan serve` before considering this task done.

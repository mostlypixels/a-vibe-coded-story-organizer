# Task 8 — Alpine adapter + `x-autosave-field` component + dirty-only + `localStorage` mirror

## Scope

* `resources/js/autosave/field.js` — the thin Alpine component adapter: wires
  debounce/blur/Ctrl-S listeners onto a `<x-wysiwyg>`/`<textarea>` instance, tracks the
  **dirty flag** (set on the first real edit event; nothing fires before it's set),
  calls `axios.patch('/autosave/{entity}/{id}/{field}', ...)`, feeds responses through
  task 7's `store.js` for state transitions, updates a shared `Alpine.store('autosave')`
  (for task 9's global badge), and mirrors the pending value to `localStorage` keyed
  `type:id:field` (or `new:<type>:<parent-id>:<field>` on create forms — `handoff.md`
  §9.1), clearing it on success.
* `resources/views/components/autosave-field.blade.php` — the `x-autosave-field`
  wrapper: label row (label + history icon-link, reusing the existing icon-link style),
  the editor (`<x-wysiwyg>` for `Rich`/`Markdown` kinds via `AutosavableFields::kindOf()`,
  a new **`plain` kind** rendering a raw `<textarea>` for `Project.rights`),
  `<x-input-error>`, the field's inline indicator, and the `data-hash` attribute seeded
  from the page render (`hash('sha256', $model->{$field} ?? '')`) so `base_hash` starts
  correct without an extra round trip.
* The `localStorage` restore banner (inline, per field, never a modal): on mount, checks
  for a surviving draft and renders Restore/Discard or Compare/Discard-only per task 7's
  triage decision.

Does **not** include: wiring this component into any *existing* Blade view (task 9 —
this task only builds the component and proves it works via a standalone/isolated
usage or component test), the global lower-right badge itself (task 9 renders it; this
task only writes to the shared store it reads from), or the `session-expired`
sign-in-in-new-tab flow's exact copy (task 9, since that's presentation, though the
state itself comes from task 7).

## Depends on

Task 6 (real endpoint contract), task 7 (`store.js`).

## Key decisions already made

* **Dirty-only**: no PATCH of any kind — not even a debounce tick — fires until the
  writer has produced a real edit event in this field. Opening a record for reading
  triggers nothing.
* **The client never computes a hash of what it sends**, and never writes a PATCH
  response's `value` back into the live editor DOM (would yank the caret mid-sentence,
  per `handoff.md` §9.13) — it only adopts the returned `hash` for the next `base_hash`.
* **Every pending value is mirrored to `localStorage`** before the request fires, and
  cleared only on a **successful** (200) response — so a crash/close mid-request always
  leaves a recoverable draft.
* **The `plain` kind is new** — `wysiwyg.blade.php` (read this session) has no such
  mode; `Project.rights` is a raw `<textarea>` at `resources/views/projects/edit.
  blade.php:73` today and must stay a plain textarea, not be forced into the rich
  editor.
* **Ctrl-S is a flush + window-close, not a permanent checkpoint** — it sends
  `run_matcher=true` but not `manual=true` (only the real form Save button sets
  `manual=true`).

## Consult

* `expanded/ui.md` — "`x-autosave-field` component", "Autosave client module",
  "`localStorage` restore banner" sections.
* `resources/views/components/wysiwyg.blade.php` (already read this session) — match
  its progressive-enhancement pattern (real `<textarea>` first, Alpine mounts over it,
  `x-show="! ready"` / `style="display:none"`, no `x-cloak`).
* `handoff.md` §3.4, §9.1, §9.4, §9.7, §11.5.1 (dirty-only).

## Tests

* Vitest, co-located with `field.js` where the logic is DOM-free enough to unit test
  (e.g. the dirty-flag gating function, the `localStorage` key-building for both the
  existing-entity and `new:` create-form shapes) — anything requiring real DOM/Alpine
  lifecycle is left to the manual checklist per `handoff.md` §9.12, not faked in vitest.
* A PHPUnit or Blade-rendering check (whatever this project's convention is for
  asserting component output — check for an existing precedent like a `wysiwyg`
  component test before inventing one) that `<x-autosave-field kind="plain">` renders a
  bare `<textarea>`, not the `x-wysiwyg` Alpine root.
* Assert the `data-hash` attribute on initial render equals `hash('sha256', $model->
  {$field})` for a field with a known value.

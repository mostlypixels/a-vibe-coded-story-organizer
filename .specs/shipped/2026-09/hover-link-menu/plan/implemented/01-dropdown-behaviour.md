# 01 — Dropdown behaviour

## Scope

- `resources/views/components/dropdown.blade.php` and `app/View/Components/Dropdown.php`.
- Escape closes any open dropdown and focuses its `x-disclosure-button`.
- One open at a time: opening a dropdown dispatches a window event with its `disclosureId`. Every other dropdown closes on it.
- New `hover` prop (default `false`):
  - The trigger wrapper does not toggle on any click. Only the disclosure button toggles, so a link in the trigger slot navigates without opening the menu.
  - `pointerenter` / `pointerleave` on the wrapper (trigger + panel), mouse only, with the 250 ms close delay. A re-enter cancels the pending close.
- `documentation/interface/components.md`: document `hover`, Escape, and one open at a time.
- Deferred: no view uses `hover` yet. Task 02 is the first caller.

## Depends on

Nothing.

## Key decisions

- See `00-overview.md` → Binding decisions (timing, mouse only, Escape, one open).
- Keep the Alpine inline while it stays short. If it grows past a few lines, move it to `resources/js/` as `Alpine.data` with a Vitest file.
- Check every `x-dropdown` caller for a text field or a key handler inside the panel. Escape must not break it. Log any conflict in `resolution-log.md`.

## Docs

- `expanded/ui.md` → Components.

## Tests

- Feature: a `hover` dropdown renders the pointer handlers. A default dropdown does not, and keeps its click toggle.
- `tests/Feature/DisclosureToggleTest.php` still passes.
- Browser (Playwright, Firefox and Chromium): Escape closes the user menu and focus is on its button. Opening one dropdown closes another. The hover check waits for task 02, the first real caller.

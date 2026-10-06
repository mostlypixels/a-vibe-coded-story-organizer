---
title: "Task 02 — Dropdown closeOnClick prop"
---

# Task 02 — Dropdown closeOnClick prop

## Scope

- `App\View\Components\Dropdown`: new constructor parameter `public readonly bool $closeOnClick = true`.
- `resources/views/components/dropdown.blade.php`: print the panel's `@click="close()"` only when
  `$closeOnClick` is true.
- `documentation/interface/components.md`: one line on the prop and when to use it (a panel with
  form controls).

Does **not** use the prop anywhere. Task 06 does.

## Depends on

Nothing.

## Key decisions already made

- Extend `x-dropdown`; do not use `x-popover`. The switcher needs the one-open-at-a-time event and
  the Escape focus return from `dropdown.js`.
- `dropdown.js` needs no change.

Detail: `expanded/architecture.md` → *JavaScript* (the warning).

## Tests

- Feature or view test: `<x-dropdown>` renders the panel `@click="close()"`;
  `<x-dropdown :close-on-click="false">` does not.

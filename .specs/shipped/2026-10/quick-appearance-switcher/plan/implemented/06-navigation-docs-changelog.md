---
title: "Task 06 — Navigation button, docs, changelog"
---

# Task 06 — Navigation button, docs, changelog

## Scope

- `resources/views/layouts/navigation.blade.php`:
  - Desktop: an `x-dropdown align="right" :close-on-click="false"` left of the account menu.
    Trigger `x-disclosure-button` with `x-tabler-palette`, `aria-label` "Appearance". Panel body
    starts as a "Loading…" line, `aria-live="polite"`. The loader from task 05 sits on it.
  - Mobile: the same button beside the hamburger.
  - Neither renders on `admin.appearance.edit`. Put that check in `ProjectNavigation` (for
    example `showsAppearanceSwitcher()`), not in Blade.
- Prefer one Blade component (`x-navigation.appearance-switcher`) used in both places, so the
  markup exists once.
- Docs: a short "Quick switcher" section in `documentation/interface/themes.md` and `fonts.md`.
  Name the shared preview map and the one-field `PATCH`. Add `x-theme-swatch` to
  `components.md`.
- `CHANGELOG.md`: one dated section.
- Manual check with `run-imagoldfish` (Playwright).

## Depends on

02, 03, 04, 05.

## Key decisions already made

- Click-only dropdown, no `hover`.
- `layouts/error-navigation.blade.php` is not touched.
- Trigger classes match the book menu button: `h-12`, `text-nav-content`, focus ring.

Detail: `expanded/ui.md` → *Trigger*; `expanded/overview.md` → *Acceptance criteria*.

## Tests

Feature (new or in `AppearanceSwitcherTest`):

- An app page has the palette button with `aria-controls` and the switcher URL.
- The page HTML holds no swatch markup (the panel is not inlined).
- `admin.appearance.edit` has no palette button.
- An error page (for example a 404 for a signed-in user) has no palette button.

Manual (Playwright):

- Pick each swatch; reload; the theme holds.
- Manuscript `+` to the largest step; `+` is disabled. Same for spacing.
- Keyboard only: Tab to the button, Enter opens, arrows move between swatches, Escape returns
  focus to the button.
- Phone width: the button shows beside the hamburger and the panel fits the screen.

# 02 — Section links

## Scope

- New `resources/views/components/navigation/menu-link.blade.php`: an `<a>` plus an `x-disclosure-button` chevron. Props: `href`, `active` (underline), `current` (`aria-current="page"`), `menuLabel` (chevron accessible name).
- `resources/views/components/navigation/project-menu.blade.php`: Story, Timeline, Codex and Tools use `x-dropdown hover` with `menu-link`. Link targets: `books.story.home`, `projects.timeline.home`, `projects.codex.home`, `projects.tools.home`.
- Remove the first item that repeats the parent ("Story" in Story, and so on).
- Delete `x-navigation.dropdown-trigger` if nothing uses it after this task.
- `documentation/interface/components.md`: add `x-navigation.menu-link`.
- Deferred: the user menu and the account page (task 03).

## Depends on

01.

## Key decisions

- `current` reads the existing `storyHomeActive`, `timelineHomeActive`, `codexHomeActive`, `toolsHomeActive`. No new flags.
- Chevron hit area: full nav height (`h-12`) and at least 24px wide.
- Chevron names from `00-overview.md`.
- **Chevron click after a hover-open pins the menu open.** It no longer closes when the pointer leaves. A second chevron click closes it. Change `resources/js/dropdown.js` and its Vitest file for this.

## Docs

- `expanded/overview.md` → Scope, Acceptance criteria.
- `expanded/ui.md` → Components, Views, Active state.

## Tests

- `tests/Feature/NavigationTest.php`: change `assertTriggerIsActive` / `assertTriggerIsNotActive` to match the `<a>`.
- Each section renders an `<a>` to its hub route and a named chevron button.
- The hub link has `aria-current="page"` on its hub page, not on a child page. The underline shows on both.
- No menu contains a link to its own hub route.
- Browser (Firefox and Chromium): one click on "Story" loads the Story page. A mouse hover opens the menu, and it stays open on the way to an item. A touch tap (`hasTouch`) navigates and does not open it. Tab to the chevron, Enter opens, Escape closes. At 640px the nav fits on one line.

# Hover link menu — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- Grill 2026-09-27, all recommended answers accepted:
  - Hover opens at once and closes 250 ms after leaving.
  - Focus does not open a menu; the chevron does (APG disclosure navigation pattern).
  - One menu open at a time.
  - The error-page nav uses the same user-menu component.
  - Chevron names: "Story menu" … "Account menu".
  - New page: "Account" at `/account`.
  - Escape closes every `x-dropdown` and returns focus to its button.
- After task 01: a chevron click after a hover-open pins the menu open; a second click closes it. A click means the user wants the menu, so closing it feels broken. Added to task 02.
- After the plan shipped, Cindy reversed the chevron pin: a mouse user saw a hover-open menu, clicked the chevron, and nothing visible happened. The chevron now points up while the menu is open, and a click on it always toggles. A click-opened menu still stays open when the pointer leaves.
- Draft stage: the book switcher stays as it is. Log Out stays in the menu. Every user sees the Configuration card.

## Deviations from the spec/plan

- Task 03: the browser `<title>` on `/account` is the bare app name, not "Account". `PageTitle` builds only from `$navigation->routeProject`/`routeBook`; `/account` carries neither, same as `/onboarding` (see `PageTitleTest::test_pages_outside_a_project_show_the_app_name_alone`). Giving off-project routes a name-based title needs a broader `PageTitle` change, out of this task's scope. The `x-page-heading` "Account" carries the on-page name instead.

- Task 01: Escape moves focus to the disclosure button only when focus is inside the dropdown. A menu opened by hover while focus is elsewhere (for example the editor) closes, but focus stays. Moving it would steal the writer's caret.
- Task 01: the dropdown Alpine grew past a few lines, so it is `Alpine.data('dropdown')` in `resources/js/dropdown.js` with `dropdown.test.js`, as the task allowed.
- Task 02: the chevron click pin is a `pinned` flag in `dropdown.js`. Any chevron click in hover mode (keyboard, touch or mouse) pins the menu, so a keyboard-opened menu does not close when the mouse passes by. `close()` clears it.
- Task 02: the section underline sits on a wrapper around link and chevron. Tests assert `data-active` on the `<a>`, not class strings.

## Issues → resolutions

- Task 02: at 640px the desktop nav overflows by about 234px (Chromium and Firefox), so the "fits on one line" check fails. Root cause predates this feature: the book switcher, Dashboard and Search already overflow. Each section is exactly as wide as the old trigger (label + 28px), so this task adds no width. Not fixed: the responsive menu is out of scope. Needs its own task.
- Task 01: an in-dropdown Escape stops propagation, so an `x-modal` around a dropdown (window Escape listener) stays open. No `x-dropdown` panel has a text field or its own Escape handler; no conflict found.
- Task 03: making the user menu `x-navigation.menu-link` + `hover` added a fifth `[data-hover]` dropdown to the page, which broke `NavigationTest::test_each_section_is_a_hub_link_plus_a_named_chevron` (it asserts `array_keys($hubs) === array_keys($sections)` over every `[data-hover]` on the page). Root cause: `sections()` scanned the whole document, not just the Story/Timeline/Codex/Tools group. Fix: gave that group's wrapper `id="section-links"` in `layouts/navigation.blade.php` and scoped the test helper to it.

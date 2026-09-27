# Testing

## Change existing tests

- `tests/Feature/NavigationTest.php`: `assertTriggerIsActive` / `assertTriggerIsNotActive` match `<button … border-accent>Label`. Change them to match the `<a>`.
- `tests/Feature/DisclosureToggleTest.php`: the app and error nav toggles still name their panels. The chevron is now the toggle.

## New feature tests

- Each of Story, Timeline, Codex and Tools renders an `<a>` to its hub route.
- On a hub page, its link has `aria-current="page"`. On a child page (e.g. Scenes), it does not, but the underline shows.
- The menus contain no link to their own hub route.
- The user menu renders an `<a>` to `account`. It is underlined on `account`, `profile.edit` and `admin.settings.edit`.
- `GET /account`: a guest is redirected to login. A logged-in user gets 200, and the page links to `profile.edit` and `admin.index`.
- The account page has no Log Out form.
- The chevron button has an accessible name.

## Browser check (Playwright, Firefox and Chromium)

New script in `.claude/skills/run-imagoldfish/chromium_cli/ft/`:

- A click on "Story" loads the Story page in one click.
- A mouse hover opens the menu. Moving onto a menu item keeps it open. Moving away closes it.
- A touch tap (`hasTouch` context) on the link navigates and does not open the menu.
- Tab to the chevron and press Enter: the menu opens. Press Escape: it closes, and focus is on the chevron.
- At 640px width, the nav fits on one line.

# Overview

The Appearance page (`admin.appearance.edit`) is three clicks deep and needs a Save. A writer who
wants a darker theme at night, or a bigger manuscript font, must leave the scene. The switcher
puts the common changes one click from any app page.

## Goals

- Palette icon button in the top bar, left of the account menu.
- The panel holds:
  - one small swatch per theme preset; the name is in a tooltip;
  - interface font: family `<select>`, size −/+, line spacing −/+;
  - manuscript font: family `<select>`, size −/+, line spacing −/+.
- Each change applies on the page at once and saves at once. No Save button.
- The panel markup loads over AJAX on first open. Page HTML gets only the button.

## Non-goals

- Locale, accessible-font marks, reset to defaults. They stay on the Appearance page.
- The button on the Appearance page itself. A switcher change there makes the page form stale,
  and its Save puts the old value back.
- A replacement for the Appearance page. The panel links to it ("More settings").
- Error pages (`layouts/error-navigation.blade.php`). They make no AJAX calls.
- Public share pages and guests. No user, no preference.
- New presets, families or scale steps.

## User stories

- At night I click the palette and pick a dark swatch. The page turns dark, and the next page
  is dark too.
- My eyes are tired. I click manuscript `+` twice. The prose grows at once.
- I hover a swatch and see its name before I pick it.

## Acceptance criteria

- The button shows on every page that uses `layouts/navigation.blade.php`, at every width,
  except `admin.appearance.edit`.
- The first open fetches the panel. Later opens on the same page reuse it.
- A swatch click, a font pick, a size step or a spacing step:
  - repaints the page from the server-approved preview map;
  - sends one `PATCH` with only that field;
  - after reload, the server renders the same value.
- `−` is disabled on the smallest step. `+` is disabled on the largest.
- A failed save puts back the last saved value and shows a short error in the panel.
- Keyboard: Tab reaches the button. Enter opens the panel. Arrow keys move between swatches.
  Escape closes the panel and returns focus to the button.
- A non-owner case does not exist: every write goes to the acting user.

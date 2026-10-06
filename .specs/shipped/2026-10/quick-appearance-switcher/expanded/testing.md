# Testing

## Feature: `tests/Feature/AppearanceSwitcherTest.php`

- Guest → `admin.appearance.switcher` redirects to login.
- Signed-in user → 200. The response is a fragment: no `<html>`, no layout navigation.
- Every preset shows as a swatch. The active one is checked. A user with `null` columns sees the
  configured defaults checked.
- Every family shows in both selects. The active ones are selected.
- The config carries the ordered `ui_scales`, `manuscript_scales` and `leading` slugs.
- A stored slug no longer in config falls back to the default; no exception.

## Feature: extend `tests/Feature/AppearanceSettingsTest.php`

- `PATCH` with `Accept: application/json` → 204, column saved.
- JSON `PATCH` with a tampered slug → 422 JSON, column unchanged.
- **Partial `PATCH` guard:** send only `theme_slug`. Every font column stays unchanged. Then send
  only `manuscript_scale`; `theme_slug` stays unchanged.
- One user's JSON `PATCH` leaves another user's columns alone.
- The form `PATCH` still redirects with the `theme-updated` flash.

## Layout

- An app page shows the palette button with `aria-controls` and the switcher URL.
- An error page has no palette button.
- `admin.appearance.edit` has no palette button.
- The page HTML holds no swatch markup (the panel is not inlined).

## Unit

- `AppearancePreviewMap::build()`: same keys and values as the old Blade map. Theme entries equal
  `ThemeStyleBlock::declarations()`.
- `ThemePreset::swatch()`: drops a value that fails `Oklch::CSS_VALUE_PATTERN`.
- `ScaleLabel::format()`: `100%` → `16px`; `115%` + `times` → `1.15×`; `1` → `1×`.

## JavaScript: `resources/js/appearance-switcher.test.js` (Vitest)

- `step()` clamps at both ends and returns the neighbour slug.
- An unknown slug applies nothing and sends nothing.
- Queue: two quick changes to one field send in order. A third queued change replaces the
  second.
- Failure re-applies the last saved slug and sets the error text.
- `load()` fetches once; a second open makes no request.

## Dropdown

- `x-dropdown` with `close-on-click="false"`: a click inside leaves it open. Default stays as now.

## Manual (run-imagoldfish, Playwright)

- Pick each swatch; reload; the theme holds.
- `+` to the largest step; `+` is disabled.
- Keyboard only: open, arrow through swatches, Escape returns focus to the button.

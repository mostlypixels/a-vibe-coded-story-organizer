# Testing

## Unit

- `tests/Unit/Support/PageWidthTest.php`: `resolve()` for null, each step, 55, 0, 110 → default
  for the off-list ones. `css(70)` is `70%`.
- Config test (next to `FontConfigTest`): default is in steps; steps ascending; all in 1–100.
- `AppearancePreviewMapTest`: has `page_width`, keyed by every step.

## Feature — `tests/Feature/AppearanceSettingsTest.php`

- PATCH `admin.appearance.update` with `page_width = 50` saves it; JSON request gets 204.
- Rejects 55, 45, 110, `"wide"`; null resets to default.
- An app page renders `--page-width:50%` for a user who saved 50, and the default for null.
- A stored off-list value (set directly on the model) renders the default, no error.
- Guest is redirected to login (existing route middleware; one assertion).
- Saving `page_width` does not change other appearance columns.

## Feature — `tests/Feature/AppearanceSwitcherTest.php`

- The fragment has the width row, and its `active.page_width` is an integer.

## JS — `resources/js/appearance-switcher.test.js`, `font-preview` tests

- `neighbourSlug([50,…,100], 50, -1)` stays 50; `(…, 100, 1)` stays 100; `(…, 70, 1)` is 80.
- `resolvePreview(maps, 'page_width', 70)` returns `{ '--page-width': '70%' }`;
  an unknown step returns null.

# Architecture

## Data

No schema change. The switcher writes existing `users` columns: `theme_slug`, `ui_font`,
`manuscript_font`, `ui_scale`, `manuscript_scale`, `ui_leading`, `manuscript_leading`. No
seeding impact.

## Routes

Both sit in the existing `admin.` group in `routes/web.php`, next to the Appearance routes.

| Method | URI | Name | Action |
| --- | --- | --- | --- |
| GET | `/admin/appearance/switcher` | `admin.appearance.switcher` | `AppearanceSwitcherController` (invokable) |
| PATCH | `/admin/appearance` | `admin.appearance.update` | existing `AppearanceController@update` |

- No new write route. The switcher reuses `UpdateAppearanceRequest`.
- `validated()` holds only the fields present, so a one-field `PATCH` changes one column.
  A test must pin this; a later "fill every field" change would break the switcher silently.

## Controllers

- **`AppearanceSwitcherController`** (new, invokable). Returns the partial view
  `appearance.switcher` with no layout. Data: presets, active slugs (`ThemePreset::resolve()`,
  `FontChoice::resolve()`), families, ordered slugs of `ui_scales`, `manuscript_scales` and
  `leading`, preview map.
- **`AppearanceController@update`**. Add a `wantsJson()` branch: `response()->noContent()`.
  Same shape as `SceneController::reorderResponse()`. A failed rule gives Laravel's 422 JSON.

## Authorization

- Same as the Appearance page: `auth` middleware plus `UpdateAppearanceRequest::authorize()`.
- No policy, no `ProjectPolicy` walk. The preference has no owning project. The write target is
  always `$request->user()`, so no cross-user case exists.
- Not the `access-admin` gate. That gate is for global settings; this is a per-user preference.

## Shared logic (second caller now exists)

| Extract | From | To | Callers |
| --- | --- | --- | --- |
| Preview map | `@php` block in `admin/appearance/edit.blade.php` | `App\Support\AppearancePreviewMap::build()` | Appearance page, switcher |
| Swatch colours | `@php` block in `components/theme-card.blade.php` | `ThemePreset::swatch()` (keeps the `Oklch::CSS_VALUE_PATTERN` check) | `x-theme-card`, `x-theme-swatch` |
| Scale label (`16px`, `1.15×`) | closure in `components/setting-track.blade.php` | `App\Support\ScaleLabel::format(string $value, string $format)` | `x-setting-track`, switcher |

`AppearancePreviewMap::build()` takes `ThemeStyleBlock` so theme values still come from the
renderer that paints the saved page.

## JavaScript

`resources/js/appearance-switcher.js`, registered in `resources/js/app.js` as
`Alpine.data('appearanceSwitcher', …)`.

- Reuses `resolvePreview()` from `font-preview.js`. No second copy of `PREVIEW_PROPERTIES`.
- `load()`: on first open, `axios.get(url)` and put the HTML in the panel. Keep a `loaded`
  flag. Later opens make no request.
- `change(field, slug)`: apply the preview, then queue the `PATCH`.
- Save queue: one request in flight. A newer value for the same field replaces the queued one.
  Two quick `+` clicks then send at most two requests, never out of order.
- On failure: re-apply the last saved slug for that field, show the error line.
- `step(field, ±1)`: move along the ordered slug list from the server. Clamp at both ends.
  The same function serves the size and the spacing fields.

> [!WARNING]
> `x-dropdown` closes on any click inside its panel (`@click="close()"` in
> `components/dropdown.blade.php`). The switcher needs the panel to stay open. Add a
> `closeOnClick` prop to `App\View\Components\Dropdown` (default `true`), so the switcher keeps
> the one-open-at-a-time and Escape behaviour of `dropdown.js`.

## Invariants

- Stored slugs resolve only through `ThemePreset::resolve()` and `FontChoice::resolve()`.
- A CSS value reaches the page only from the server preview map. The partial carries the map;
  the JS rejects any slug not in it.
- Project invariants (main plotline, position ordering, project ownership) are not touched.

## Documentation

- `documentation/interface/themes.md` and `fonts.md`: a short "Quick switcher" section. It names
  the shared preview map and the one-field `PATCH`.
- `documentation/interface/components.md`: `x-theme-swatch` and the `closeOnClick` prop.

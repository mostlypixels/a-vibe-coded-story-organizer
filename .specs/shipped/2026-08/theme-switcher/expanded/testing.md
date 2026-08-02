# Theme Switcher — testing

Plain PHPUnit, `RefreshDatabase`, factories, `actingAs`, `route()` — the `ProjectTest` style.
Suite runs in parallel on in-memory SQLite, so nothing may assume shared state; the single
`theme_settings` row is created per process by the lazy `ThemeSetting::current()` path.

Presets are config, so most of this file needs no database at all.

## Unit — `tests/Unit`

### `OklchTest`

- Round-trip `fromHex()` → `toHex()` within a tolerance, on known values across the gamut.
- `relativeLuminance()` against published WCAG figures: white `1.0`, black `0.0`, and two
  mid-tones. **This is the test that catches approximating luminance with OKLCH `L`** — the
  two agree closely enough at the extremes to hide the bug, so pick mid-tones deliberately.
- Out-of-gamut input clamps rather than wrapping hue.

### `ThemeRampCommandTest` (`tests/Feature/Console`)

Thinner than a support-class suite would be, because the command's output is pasted by a human
who is looking at the numbers. Assert the properties, not the palette:

- Eleven shades, keys `50…950`, lightness strictly monotonic.
- Steps are **perceptually even**: consecutive `L` deltas within a small tolerance of each
  other — the property the old eyeballed sRGB ramps failed.
- Hue preserved across the ramp; `--max-chroma` clamps every shade at or below the cap and
  leaves under-cap anchors untouched.

### `ColorContrastTest`

- Known pairs against published ratios (white/black = 21:1, and two real token pairs).
- Symmetry: `ratio($a, $b) === ratio($b, $a)`.
- `verdict()`: `too_low` under the class floor (4.5 text / 3.0 non-text), `ok` inside the
  band, `too_high` above **the ceiling passed in** — and assert two different ceilings
  produce different verdicts for the same ratio. The ceiling is per-preset config; a test
  that hard-codes 15.0 re-freezes the thing it exists to unfreeze.

### `ThemeStyleBlockTest`

- Renders one `:root` rule containing every token in `ThemeTokens::ALL`.
- **Rejects hostile values**: `</style><script>`, `url(javascript:…)`, `expression(…)`, a bare
  `;` — each dropped, and the rendered block still parses. We emit unescaped CSS, so this is
  load-bearing on its own, independent of spec 3.

### `ThemePresetTest`

- Every preset in `config('themes.presets')` defines **exactly** `ThemeTokens::ALL` — no
  missing key (which renders an empty custom property and silently loses a color) and no
  unknown extra. This is the invariant a `saving` model hook would have guarded; as config it
  is a cheaper, earlier test.
- `fromSlug()` on an unknown slug throws; `all()` is keyed by slug.
- `contrastCeiling` falls back to `config('themes.contrast.default_ceiling')` when a preset
  declares none.

## Feature — `tests/Feature`

### `ThemeSettingTest`

- `ThemeSetting::current()` lazily creates the singleton at `config('themes.default')`.
- Two calls never produce two rows.
- A stored slug that no longer exists in config falls back to the default and logs, rather
  than throwing — a bad config edit must not white-screen every page.

### `AppearanceSettingsTest`

- Guest → redirected to login (both routes).
- Authenticated user sees the three presets and the active one marked.
- `PATCH` with a valid `theme_slug` updates the singleton and redirects with the status flash.
- `PATCH` with a slug not in config → `assertSessionHasErrors('theme_slug')`.
- **The negative case**: any authenticated user may change it (this singleton is deliberately
  outside `ProjectPolicy`), but a guest may not — assert the redirect, and assert the request
  class's `authorize()` directly, mirroring `CrawlerSettingTest`.

### `ThemeRenderingTest`

- The `<style>` block appears in `layouts/app`, `guest`, and `public`.
- Switching the active theme changes the rendered custom-property values on the next request.
  This is the assertion that matters. Do **not** assert the block's position relative to the
  Vite tag: an unlayered `:root` rule beats `@layer theme` at any source order, so a position
  assertion passes for a reason that is not the mechanism and gives false confidence.
- Emitted CSS names no hue-based variable.

### `ThemeContrastTest` — the one that earns its keep

Data-provided over **every preset in `config('themes.presets')` × every pair in
`ThemeTokens::ALL`**. Iterate config, not DB rows — config is what a developer edits when
picking colors, so that is what the guard should be pinned to:

- ratio ≥ 4.5 for pairs whose contrast class is `text`;
- ratio ≥ 3.0 for `accent`, `border`, `focus`;
- ratio ≤ that preset's declared ceiling (or the config default) — assert it **hard**. PHPUnit
  has no warning level (`markTestIncomplete` and `addWarning` mean other things, and a test
  that cannot fail is not a test). The presets are ours, so a preset breaching its own declared
  ceiling is a bug. "Warn, don't reject" governs spec 3's *user* input, not our fixtures.

Also assert the **generated** presets' four surfaces are distinct values. Two surfaces
collapsing into one is silent — nothing errors, a card just disappears into the page — and it
is exactly what a dark theme makes visible and a single light theme hides.

> [!IMPORTANT]
> Scope that assertion to Dusk and Low-glare dark. Daylight **cannot** pass it: `x-card`,
> `x-table`, `x-dropdown` and `x-modal`'s panel are all `bg-white` today, so pixel-stability
> forces `surface-raised == surface-overlay` in the default preset. Asserting distinctness
> across all presets contradicts the pixel-identical acceptance criterion in `overview.md`.

### `NoHueNamedColorsTest` — the sweep guard

A repo-scanning test in the spirit of `SpecsStatusConsistencyTest`: scan `resources/views`,
`resources/js`, `resources/css`, and `app/` for `ocean|navy|aqua|sun|flame|gray|slate` followed
by a shade number, plus `text-white`/`bg-white`, and fail with the file list.

- Scope it to those four paths — `documentation/` and `.specs/` discuss class names constantly
  (the same reason `app.css` carries `@source not` exclusions).
- Include `app/` deliberately: `bg-sun-200` is written from PHP by the search snippet builder.
- `master` is protected and every PR needs a green `tests` check, so it **cannot** land red
  across a multi-PR sweep. Land it with an explicit allow-list of not-yet-swept paths and
  shrink the list each PR — the allow-list is the checklist, and its last entry disappearing is
  the definition of done. (Do not defer the test to the final PR; then it guards nothing.)

## Regression gate

Not a PHPUnit test: diff computed styles for every element on the ~40 pages spec 1 walked,
Daylight vs `master`. Empty diff is the pixel-stability criterion. Record any accepted
difference in this feature's `standing-issues.md`, as spec 1 did.

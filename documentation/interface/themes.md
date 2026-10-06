# Themes

[Documentation](../README.md) › [Interface](README.md) › Themes

Themes replace runtime CSS custom properties. Application classes name roles, not colors.

## Token pairs

`App\Support\ThemeTokens` defines the vocabulary. `PAIRS` maps every background to valid foreground tokens.

- Add a background and its foreground together.
- Use status surface content for text and icons on a status tint.
- Use solid status content for text on a solid status fill.
- Keep borders separate from text contrast rules.

Tests reject incomplete pairs.

## Flat vocabulary

Tokens use role names such as `primary`, `surface-raised`, and `content-muted`. They do not use shade suffixes.

This keeps component intent stable when a preset changes from light to dark.

## Presets

`config/themes.php` stores preset names, token values, an optional contrast ceiling, and `image_opacity`. `users.theme_slug` stores only the selected slug.

`image_opacity` is a scalar from 0 to 1, not a colour token. `ThemePreset::$imageOpacity` defaults to 1 when a preset omits it.

`App\Support\ThemePreset::resolve()` handles `null` and unknown slugs by returning the configured default.

No theme data comes from free-form user input.

## Rendering

`App\Services\ThemeStyleBlock` validates every value against `Oklch::CSS_VALUE_PATTERN`. `x-theme-style` writes one unlayered `:root` block in each layout.

The unlayered block must outrank Tailwind’s theme layer so runtime values take effect without a rebuild.

## Quick switcher

The palette button in the top bar opens a panel of theme swatches and font controls. The panel is a fragment from `admin.appearance.switcher`, fetched on the first open.

- Preview values come from `AppearancePreviewMap::build()`, the same map the Appearance page uses. The JS rejects any slug not in the map.
- Each change sends one `PATCH admin.appearance.update` with that one field. It changes one column.
- A failed save puts the last saved value back and shows an error.
- The button is absent from the Appearance page: a change there would leave its form stale.

## Image dimming

- Dark presets dim content images so they glare less on a dark page.
- `ThemeStyleBlock::declarations()` emits `--image-opacity`, only for a float within 0 to 1. The Appearance live preview reads the same array.
- One unlayered `img` rule in `resources/css/app.css` applies it. The `var()` fallback is 1.
- Hover on an image, or on its enclosing link or button, restores full opacity. So does `:focus-visible` on that link or button.
- `data-full-opacity` on an `<img>` opts out. `x-image-lightbox` uses it.
- The rule is unlayered, so it outranks a Tailwind `opacity-*` utility on an `<img>`. Put such opacity on a wrapper.
- No transition. Opacity also dims the image border.
- Touch has no hover. The codex edit and show pages open the lightbox for full brightness.

Current values: `daylight` 1, `dusk` 1, `low-glare-dark` 0.7, `no-halation` 0.36.

On a dark preset, the value puts image white over `surface` at the OKLCH lightness of `content`. So an image glares no more than the body text. Recompute it when either token changes. Opacity blends in gamma-encoded sRGB, so the value is not simply the ratio of the two lightness values.

## Contrast

`App\Support\ColorContrast` applies:

- 4.5:1 minimum for normal text;
- 3:1 minimum for non-text controls and large text;
- optional per-preset ceiling warnings.

Floors are correctness requirements. A ceiling is a design warning for excessive contrast.

Use `php artisan theme:ramp` to generate OKLCH candidates and sRGB-fit results.

## Coupling with stored content colour

Rich-text fields let an author mark a span with a named colour (`red`, `green`, `amber`,
`blue`, `grey`) — see [Rich text](../features/rich-text.md#decorative-classes). Each name's
CSS value is an existing `PAIRS` token, not a new literal:

| Name | Token |
| --- | --- |
| `red` | `--color-danger-surface-content` |
| `green` | `--color-success-surface-content` |
| `amber` | `--color-warning-surface-content` |
| `blue` | `--color-info-surface-content` |
| `grey` | `--color-content-subtle` |

This is what buys the feature free dark mode and free per-theme correctness: `PAIRS`
already contrast-checks these five tokens against `surface`, `surface-raised` and
`surface-sunken` in every preset, and every preset keeps the same hue. The trade-off is
coupling in the other direction — changing one of these five tokens' hue changes how an
author's stored "red" or "green" looks everywhere it was used, not just the application
chrome. Weigh that before retuning them.

## Adding a token

1. Add it to `ThemeTokens`.
2. Add required foreground pairs.
3. Define it in every preset.
4. Use complete role classes in components.
5. Run theme and CSS build tests.

## Adding a preset

1. Add the preset to `config/themes.php`.
2. Supply every required token and an `image_opacity`.
3. Check floors and review ceiling warnings.
4. Test the appearance preview and stored selection.

There is no Tailwind `dark:` branch. Runtime tokens supply the complete palette for every preset.

This is why `resources/views/vendor/pagination/tailwind.blade.php` is published: Laravel's
stock paginator paints itself with fixed greys and a `dark:` branch, so it follows no preset.
A published vendor view is the place to fix that — retheming it in a wrapper is not possible,
since `->links()` renders the vendor view directly.

## Related documentation

- [Components](components.md)
- [Fonts](fonts.md)
- [Architecture](../architecture/README.md)

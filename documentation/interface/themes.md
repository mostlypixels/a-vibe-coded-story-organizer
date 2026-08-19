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

`config/themes.php` stores preset names, token values, and an optional contrast ceiling. `users.theme_slug` stores only the selected slug.

`App\Support\ThemePreset::resolve()` handles `null` and unknown slugs by returning the configured default.

No theme data comes from free-form user input.

## Rendering

`App\Services\ThemeStyleBlock` validates every value against `Oklch::CSS_VALUE_PATTERN`. `x-theme-style` writes one unlayered `:root` block in each layout.

The unlayered block must outrank Tailwind’s theme layer so runtime values take effect without a rebuild.

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
2. Supply every required token.
3. Check floors and review ceiling warnings.
4. Test the appearance preview and stored selection.

There is no Tailwind `dark:` branch. Runtime tokens supply the complete palette for every preset.

## Related documentation

- [Components](components.md)
- [Fonts](fonts.md)
- [Architecture](../architecture/README.md)

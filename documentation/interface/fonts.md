# Fonts

[Documentation](../README.md) › [Interface](README.md) › Fonts

Font choices use configuration and validated slugs. User input never becomes a CSS value directly.

## Configuration

`config/fonts.php` defines:

- family slugs and authored CSS stacks;
- bundled-file metadata;
- interface and manuscript scales;
- line-spacing multipliers;
- default values.

Configuration is appropriate because families are deployment choices, not database entities.

## Validation and resolution

Requests validate slugs with `Rule::in(array_keys(...))`. `App\Support\FontChoice::resolve()` converts stored slugs to configured values.

- `null` follows the configured default.
- An unknown stored slug also falls back.
- CSS stacks and numeric CSS values come only from configuration.

`App\Services\FontStyleBlock` writes the resolved values into the same style block as theme tokens.

## Scale and line spacing

- Interface scale changes the root font size.
- Manuscript scale is relative to the interface scale.
- Each line-spacing option is a multiplier of its surface base.
- Interface leading writes `--tw-leading` because Tailwind text utilities read that variable.

## Live preview

`resources/js/font-preview.js` receives server-generated lookup maps.

- A radio value selects a map entry.
- Unknown fields or slugs produce no declarations.
- Theme selection replaces the complete token block.
- The preview does not save data.

The controls remain native radios. Keyboard navigation and form submission work without JavaScript. Dragging a setting track only changes the selected radio.

## Bundled files

Fonts live under `public/fonts`. `scripts/fetch-fonts.sh` downloads pinned Fontsource files.

To add a family:

1. Add its configuration.
2. Add pinned download URLs.
3. Add matching `@font-face` declarations.
4. Run the font drift and public-asset tests.

## Boundaries

- Static and EPUB exports use their own styles.
- Public share pages do not use a writer’s private font choice.
- Keep `ThemeStyleBlock` validation and `FontStyleBlock` trust rules separate: theme values are pattern-validated; font values are resolved from authored configuration.

## Related documentation

- [Themes](themes.md)
- [Components](components.md)
- [Development](../development/README.md)

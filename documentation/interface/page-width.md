# Page width

[Documentation](../README.md) › [Interface](README.md) › Page width

Page width sets how much of the window the page content fills. It is a share of the window, not of the text size, so a larger font does not change it.

## Steps

`config/page_width.php` defines the steps (50, 60, 70, 80, 90, 100 percent) and the default (70).

- From the `md` breakpoint (768 px) the content is as wide as the step. Below it, the content is full width.
- The step is an integer in the `users.page_width` column. `null` follows the default.
- One control sets the width. The manuscript follows the page.
- Navigation, breadcrumbs, guest, error and share pages ignore the setting.

## Validation and resolution

`App\Http\Requests\UpdateAppearanceRequest` accepts only a configured step or `null`.

`App\Support\PageWidth` is the only place that turns a step into CSS:

- `resolve()` returns the stored step, or the default when the value is `null` or not a step.
- `css()` returns the CSS value, such as `70%`.
- `options()` returns every step with its CSS value. The Appearance page, the preview map and the switcher all read it.

`x-theme-style` writes the resolved value as `--page-width` in the unlayered `:root` block. `resources/css/app.css` declares the same default, so the variable always exists in the built CSS. Change the default in both places.

The label comes from `ScaleLabel::format($value, 'width')`. 100 percent reads "Full width".

> [!WARNING]
> The step must stay an integer from the column to the switcher. `neighbourSlug()` uses `indexOf`, and the Appearance track compares with `===`. A string `"70"` finds no step, and the − / + buttons stop.

## Where users change it

- The Appearance page has a "Page width" track in the "Layout" fieldset. The page is the preview.
- The quick switcher has a "Width" row with "Narrower page" and "Wider page" buttons. It stays visible on phones, where the change has no visible effect.

Both use the font preview and save path. See [Fonts](fonts.md#live-preview).

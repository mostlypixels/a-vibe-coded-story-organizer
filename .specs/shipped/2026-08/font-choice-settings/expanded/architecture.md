# Architecture

Mirror `theme-switcher` file for file. Anything below that has no reason stated is "because
the theme feature does it that way, and two mechanisms for one `<style>` block is one too
many".

## Config, not database

`config/fonts.php` — the whole vocabulary:

```php
'default_ui' => 'inter',
'default_manuscript' => 'inter',
'default_scale' => 'normal',
'default_leading' => 'normal',

'families' => [
    'inter' => [
        'name' => 'Inter',
        // The full CSS font-family list, already quoted. Rendered verbatim; the
        // slug is what gets validated, so this string is authored, never input.
        'stack' => "Inter, ui-sans-serif, system-ui, sans-serif",
        'bundled' => true,     // false = no @font-face, no woff2 (Arial, Georgia, …)
        'note' => 'Familiar sans; assumes no visual impairment.',
    ],
    // atkinson, lexend, literata, source-serif-4, arial, verdana, georgia, system
],

'scales'  => ['normal' => '100%', 'large' => '112.5%', 'larger' => '125%'],
'leading' => ['tight' => '1.4', 'normal' => '1.6', 'loose' => '1.9'],
```

- `scales` / `leading` values are authored CSS, keyed by slug. The user picks the key; the
  value is never assembled from input, which is why no numeric range rule and no
  server-side unit concatenation is needed.
- **Deviation from `spec.md`,** which asked for `app/Enums/FontFamily` + `Rule::enum`. An
  enum cannot hold the stack, the note and the bundled flag without a parallel match
  expression, and `theme-switcher` already established config as the home for
  "authored data with a slug". Validation is `Rule::in(array_keys(config('fonts.families')))`
  — same shape as `UpdateThemeSettingRequest`.

`App\Support\FontChoice` is the value object: `resolve(?string $ui, ?string $manuscript,
?string $scale, ?string $leading): self`, falling back per-field to the config default when
the stored slug is `null` **or** no longer configured. Single entry point, exactly as
`ThemePreset::resolve()` — used by the style component and by `AppearanceController`.

## Rendering

`App\Services\FontStyleBlock::render(FontChoice $choice): string` emits one unlayered rule:

```css
:root{--font-sans:<ui stack>;--font-manuscript:<manuscript stack>;--manuscript-leading:1.6;font-size:112.5%}
```

- `--font-sans` is the existing Tailwind theme variable, so every `font-sans` utility in
  the app follows the UI choice with no template change.
- `--font-manuscript` is a **new** `@theme static` variable in `resources/css/app.css`
  (Daylight-style fallback value: the Inter stack), giving a `font-manuscript` utility for
  the prose surfaces.
- `font-size` on `:root` is the text scale. Tailwind sizes are rem, so one declaration
  scales the whole app — the point of the setting. Alternative (scaling only `.prose`)
  rejected: it leaves navigation, buttons and labels at 14px for someone who asked for
  bigger text.
- **Same `{!! !!}` hazard as `ThemeStyleBlock`.** Here the guarantee is different and
  stronger: nothing interpolated is user input at all — the slug indexes a config array,
  and a slug not in that array never resolves. Say so in the class docblock; do not copy
  `ThemeStyleBlock`'s "we validate the value" wording, which would be a lie about the
  mechanism.

`resources/views/components/theme-style.blade.php` renders both blocks inside its single
`<style>`. Keep it one component: a second `<style>` tag in four layouts is the drift the
existing comment warns about.

## Font files

`public/fonts/`, referenced by absolute `/fonts/…` URLs from `@font-face` blocks in
`resources/css/app.css`. **Not Vite-bundled** — `spec.md` says "bundled through Vite"; the
shipped reality is checked-in woff2 files and hand-written `@font-face`. Follow the shipped
reality.

- Atkinson today is 12 files (6 weights × latin/latin-ext static). Repeating that four
  times is ~48 more files.
- Use **variable** woff2 for the four new families (Inter, Lexend, Literata, Source
  Serif 4 all ship one): 2 files per family per style, `font-weight: 200 700` in one
  `@font-face`.
- **Italic is load-bearing for a manuscript** — fiction is full of it, and a family with no
  italic face gets a synthesised oblique. Ship italic for the two serifs and Inter. Atkinson
  Next has no italic in the current set; that stays true and is a known cost of choosing it.
- `bundled => false` families (Arial, Verdana, Georgia, system stack) get no `@font-face`
  and no file — that is the entire reason they are on the list.

> [!WARNING]
> `@font-face` for a family nobody selects still ships the CSS but downloads nothing —
> `font-display: swap` fetches lazily per `unicode-range`. Bundle cost is repo size and
> build output, not page weight.

## Controller & routes

Extend `AppearanceController` and `admin.appearance.*` rather than adding a route pair:
the page is "Appearance & accessibility" and fonts are the other half of it.

- `edit()` passes the family list, the scale/leading lists, and the resolved active values.
- `update()` stays `$request->user()->update($request->validated())`.
- `UpdateAppearanceRequest` — rename of `UpdateThemeSettingRequest` (it now validates five
  fields, and the old name lies). Same `authorize(): $this->user() !== null`, and the same
  reason: the action writes only to the acting user, so there is no cross-user case. Keep
  that docblock; it is the documented exception to the ProjectPolicy walk.
- Every rule is `['nullable', Rule::in(array_keys(config('fonts.<list>')))]`.

## Surfaces that must *not* follow the choice

- `resources/views/exports/epub/styles.css` and `resources/views/exports/book/layout.blade.php`
  — an export is a file someone else reads on their own device. Leave them alone; they do
  not see `:root` anyway.
- The public share page renders defaults because it resolves from `auth()->user()`, which is
  `null` there. No special-casing needed — the same line as `<x-theme-style />` today.

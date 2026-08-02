# 02 — Token vocabulary and style block

## Scope

The mechanism. After this task the app paints from role tokens, but **nothing is renamed yet**
— the old hue ramps stay in `app.css` and every template still uses them.

- `app/Support/ThemeTokens.php` — three flat consts: `ALL`, `PAIRS`, `NON_TEXT`.
- `config/themes.php` — `default`, `contrast` floors + `default_ceiling`, and **Daylight only**,
  holding the current literal hex values.
- `app/Support/ThemePreset.php` — readonly value object (`slug`, `name`, `tokens`,
  `contrastCeiling`) with `fromSlug()` and `all()` reading config.
- `app/Services/ThemeStyleBlock.php` — `render(ThemePreset): string`, one `:root { … }` rule.
- `resources/views/components/theme-style.blade.php` — emits it.
- `resources/css/app.css` — add the role tokens under plain `@theme`, **alongside** the
  existing ramps.
- Wire `<x-theme-style />` into `layouts/app`, `layouts/guest`, `layouts/public`, and `welcome`.

Does **not** include: the ramp command or a second preset (03), `users.theme_slug` or the
picker (04), any rename (05+), deleting the old ramps (11).

## Depends on

01.

## Key decisions already made

- **Plain `@theme`, never `@theme inline`.** `inline` bakes values into utility rules and
  silently breaks every runtime override — the failure mode that looks like "the switcher does
  nothing" with no error anywhere.
- If a token is not referenced by any generated utility, v4 tree-shakes it out of `:root`.
  Declare the block `@theme static` if that bites.
- **Never wrap the emitted block in `@layer`.** An unlayered `:root` rule outranks
  `@layer theme` at any source order — that, not placement after `@vite`, is the mechanism. Put
  this in the component's comment.
- `ThemeStyleBlock` whitelist-validates each value against a strict hex / `oklch()` pattern and
  drops failures. It emits unescaped CSS into `{!! !!}`; that is the reason, on its own.
- No cache. Default store is `database`, so caching costs a SQL round-trip per render.
- Resolution is `auth()->user()?->theme_slug ?? config('themes.default')`. The column does not
  exist yet, so this task reads the config default only — task 04 adds the first half.

## Consult

`expanded/data-model.md` (token list, config shape), `expanded/architecture.md` (renderer, CSS
rules), `expanded/ui.md` (the component).

## Tests

`tests/Unit/ThemeStyleBlockTest`:
- Renders one `:root` rule containing every token in `ThemeTokens::ALL`.
- Drops hostile values — `</style><script>`, `url(javascript:…)`, `expression(…)`, a bare `;` —
  and the block still parses.

`tests/Unit/ThemePresetTest`:
- Every preset in config defines **exactly** `ThemeTokens::ALL` — no missing key (which renders
  an empty custom property and silently loses a color) and no unknown extra.
- `fromSlug()` throws on an unknown slug; `all()` is keyed by slug.
- `contrastCeiling` falls back to `config('themes.contrast.default_ceiling')`.

`tests/Feature/ThemeRenderingTest`:
- The block appears in `layouts/app`, `guest`, and `public`.
- **Do not** assert its position relative to the Vite tag — that passes for a reason which is
  not the mechanism.

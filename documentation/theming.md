# Theming — deep dive

The short version lives in [`architecture.md` → Theming](architecture.md#theming). This
page is the reference: the vocabulary rule, the CSS mechanism, the contrast rules, and how
to extend either.

## The paired-token rule

A class names what a colour is **for** (`bg-surface`, `text-content-muted`), never which
hue it happens to be. `bg-ocean-600` says a thing is blue; it says nothing about where it
may be used, so it becomes a lie the moment a theme flips.

`App\Support\ThemeTokens::PAIRS` is the enforcement mechanism: it maps every background
token to the foreground token(s) that may be painted on it, chosen together at the moment
the token is introduced. `ThemeTokens::ALL` must appear in `PAIRS` — as a key or inside a
list — or `ThemePresetTest` fails, so a new token cannot ship without a partner. That is
the whole point: an unreadable combination is unrepresentable, not merely discouraged.

Two tokens carry no partner and no contrast floor at all — `border` (a hairline between
table rows or around a card; WCAG 1.4.11 covers what identifies a component or its state,
which a divider is not) and `scrim` (the modal backdrop, an effect rather than a surface
anything is painted on). Both are named in `ThemeTokens::DECORATIVE`, which the contrast
matrix (`ThemeContrastTest`) skips. `border-strong` and `focus` are *not* exempt — they do
identify a control or its state, so they keep the 3:1 floor.

## The flat vocabulary — no shade suffixes

There is `bg-primary`, never `bg-primary-600`. Ramps (`php artisan theme:ramp`, below) are
an *authoring* tool that produces eleven shades to choose from; once chosen, a preset
stores one flat, final value per token and Blade only ever names the role.

This is the rule most likely to be broken by habit — reaching for `bg-primary-600` because
that's the muscle memory from before this feature shipped. There is no such class; it
compiles to nothing, silently.

## Presets are config, not database rows

`config/themes.php` holds exactly three presets — **Daylight** (the app's original light
look), **Dusk** (a dimmed light theme, no white anywhere), and **Low-glare dark** (a
genuine dark theme with a low contrast ceiling to avoid halation). Nothing about a theme
varies per request beyond which preset is active, so there is no `themes` table and no
settings singleton — only `users.theme_slug` (nullable) varies at runtime.

- `null` means "follow the default" (`config('themes.default')`), so changing the default
  still reaches everyone who never picked a preset.
- `App\Support\ThemePreset::resolve(?string $slug)` is the single entry point from a
  stored slug to a `ThemePreset` — used by both `<x-theme-style />` and
  `AppearanceController`. It falls back to the default both when `$slug` is `null` and
  when it no longer matches a configured preset (a user picked one that was later removed
  from config) — a stale value must never throw and white-screen every page.
- The picker lives at `/admin/appearance` (`AppearanceController` +
  `UpdateThemeSettingRequest`), inside the existing Configuration area. It writes only to
  `$request->user()`, so — like `CrawlerSetting` and `ImportSetting` —
  `authorize()` is `$this->user() !== null`, not a `ProjectPolicy` walk: there is no
  cross-user case, and the preference is owned by no `Project`.
- Unauthenticated surfaces (`/`, login, the public scene-share page) always render the
  config default.

## Rendering — `<x-theme-style />`

Every layout includes one component, once, in `<head>`:

```blade
<x-theme-style />
```

It renders `App\Services\ThemeStyleBlock::render()` for the resolved preset: one
unlayered `:root { --color-token:value; … }` rule, printed with `{!! !!}`.

- **Why unlayered wins.** Tailwind compiles `@theme` into `@layer theme { :root { … } }`.
  An *unlayered* `:root` rule outranks any cascade layer regardless of source order (CSS
  Cascade 5) — that ranking is the entire mechanism. Wrapping this block in `@layer`
  anywhere silently loses; nothing errors.
- **Why it's safe to print unescaped.** Escaped CSS is not CSS. `ThemeStyleBlock`
  whitelists every value against `Oklch::CSS_VALUE_PATTERN` (`#rrggbb` or
  `oklch(l c h)`) before printing it; a value that fails is dropped, so the token falls
  back to the compiled `@theme` default — a visibly wrong colour, never broken markup or
  an injected `</style>`.
- **No cache.** The default cache store is `database`; caching would trade ~40 `sprintf`
  calls for a SQL round-trip per render.

`resources/css/app.css` also declares the whole vocabulary once, as `@theme static`
(`static`, not plain `@theme` — Tailwind 4 tree-shakes a theme variable it sees no
utility reference to, and nothing referenced these until the sweep renamed the
templates). Its values are Daylight's, copied literally — a fallback for the rare
response with no `<x-theme-style />` block, never the source of truth.
`ThemePresetTest::test_the_compiled_theme_block_matches_the_daylight_preset` parses that
block back out of `app.css` and asserts it matches `config('themes.presets.daylight.tokens')`
token-for-token, so the two copies cannot drift.

> [!WARNING]
> **Never `@theme inline`.** `inline` bakes a token's value directly into each utility
> rule instead of referencing the custom property — which silently breaks every runtime
> override. The failure mode is "the switcher does nothing, no error anywhere," which is
> what makes it worth calling out on its own.

## Contrast — floors reject, ceilings warn

`App\Support\ColorContrast` measures WCAG contrast ratios and judges them against a floor
and a ceiling:

- **Floors are fixed WCAG minimums**, the same for every preset:
  `ColorContrast::TEXT_FLOOR` (4.5:1, any text-on-background pair) and
  `ColorContrast::NON_TEXT_FLOOR` (3:1, non-text UI — `ThemeTokens::NON_TEXT`:
  `accent`, `border-strong`, `focus`). A pair below its floor is rejected —
  `ThemeContrastTest` asserts every pair in `ThemeTokens::PAIRS`, across every preset,
  lands inside its band.
- **The ceiling is per-preset and a taste judgement, not a correctness one.** More
  contrast is not monotonically better: white on near-black is 21:1 and is *worse* for
  astigmatism, where halation makes light text bloom into the background. Different
  conditions pull the ideal in different directions — a low-vision preset would want a
  *higher* ceiling, a low-glare one a lower one — so each preset may declare its own
  `contrast_ceiling` (`config/themes.php`), defaulting to `themes.contrast.default_ceiling`
  (15.0). A ceiling below the applicable floor is clamped up to it, so no ratio can be
  judged both `TooLow` and `TooHigh`.
- Today the ceiling only warns for a *user's* own colour choice (not built yet — a later
  spec); for the three presets shipped here, PHPUnit has no warning level, so
  `ThemeContrastTest` asserts the ceiling as a rejection too. A preset that breaches its
  own declared ceiling is a bug.

## Adding a token

1. Add the name to `App\Support\ThemeTokens::ALL`, grouped with its family, and to
   `PAIRS` under the background(s) it may be painted on (or the reverse, if it's a
   background).
2. If it's non-text UI (a ring, a divider that identifies state), add it to `NON_TEXT`.
   If it carries no text and needs no floor at all, add it to `DECORATIVE` instead —
   sparingly; that list is meant to stay short and explicit.
3. Add a value for it to **every** preset in `config/themes.php`. `ThemePresetTest`
   requires each preset's `tokens` to define exactly `ThemeTokens::ALL` — no missing key
   (which would render an empty custom property and silently lose a colour) and no
   unknown extra.
4. Add the matching `--color-<token>` declaration to the `@theme static` block in
   `resources/css/app.css`, with Daylight's value.
5. Run the suite — `ThemeContrastTest` will fail loudly for any preset whose value you
   haven't chosen carefully yet.

## Adding or re-authoring a preset

`php artisan theme:ramp <anchor> [--max-chroma=] [--against=]* [--ceiling=] [--non-text]`
turns one anchor colour (hue + chroma) into an eleven-shade OKLCH ramp (shades 50–950,
matching the old hand-authored ramps' keys) and prints each shade's contrast ratio against
whatever backgrounds you pass to `--against` (default: white and black) — paste values
straight out of `config/themes.php` to check a shade against the *actual* surface it will
sit on.

- It is an **authoring tool, not application code** — nothing computes a ramp while
  serving a request, which is why the logic lives in `app/Console/Commands`, not
  `app/Support`. A preset stores the final, chosen values.
- Lightness moves in equal steps from 0.97 (shade 50) to 0.15 (shade 950); hue and
  chroma stay fixed from the anchor. Equal steps of OKLCH lightness are equal steps of
  *perceived* lightness, which is the property the five old hand-eyeballed sRGB ramps
  never had. 0.15 is deliberately below Daylight's darkest colour (`navy-950` ≈ 0.24): a
  dark preset needs a page background, a sunken well, *and* a raised card all below the
  midpoint.
- `--max-chroma` clamps every shade's chroma — saturated colour at high lightness is what
  makes a dark theme tiring to look at, so both generated presets cap it (~0.12–0.14).
- Each shade is gamut-fitted (`Oklch::fittedToSrgb()`, chroma reduced by binary search
  until the triple fits sRGB) *before* it's printed, so the value in the table is the
  value the browser will actually paint and the ratio beside it describes that same
  colour — not the unfitted one.
- Paste chosen shades into a preset's `tokens` array as final values, then run
  `ThemeContrastTest` and `ThemePresetTest`. Comment the anchors that produced them above
  the block, the way `dusk` and `low-glare-dark` do — the next person re-authoring the
  preset needs to know where to start, not just what it ended up as.
- The two generated presets' four *surfaces* are half-steps of one ramp, not full ramp
  shades: elevation is a much smaller lightness change than a full ramp stride, and with a
  tight ceiling (10.0 for the dark preset) the floor-to-ceiling window is barely wide
  enough to hold four surfaces and three content weights at all. Treat those values as a
  solved system, not a palette to nudge — widening one end narrows the other.

## Why there is no `dark:` variant

Dark mode is a preset (`low-glare-dark`), not a second axis crossed with every other
utility. Tailwind's `dark:` variant answers "what does this look like in the *other*
mode" for exactly two modes; this feature ships three presets today and is built to hold
more, so `dark:bg-gray-800` was never the right tool — it's also exactly the kind of
hue-named class the vocabulary exists to prevent. `@custom-variant dark` is deliberately
absent from `app.css`; do not add it back.

## Where the rationale lives

`.specs/shipped/2026-08/theme-switcher/resolution-log.md` (once the feature ships) holds
the judgement calls the sweep made file-by-file — which token a shade with no exact match
landed on, and why — plus the accepted visual differences between the old hard-coded
Daylight and the re-authored one. Read it before re-deciding one of those calls.

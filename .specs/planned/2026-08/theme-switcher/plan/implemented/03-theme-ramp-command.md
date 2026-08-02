# 03 — `theme:ramp` command and a rough dark preset

## Scope

The authoring tool, plus a **deliberately provisional** second preset so the sweep tasks have
something to switch to.

- `app/Console/Commands/ThemeRampCommand.php` — `php artisan theme:ramp "#219ebc" --max-chroma=0.12`.
  Anchor in, eleven shades out as config-ready values, with each shade's contrast verdict
  printed beside it.
- `config/themes.php` — add a `low-glare-dark` preset from that output.
- **Two vocabulary amendments, settled after task 02 shipped** (see `resolution-log.md`). Both
  land here because a preset must define exactly `ThemeTokens::ALL`, so the dark preset cannot
  be authored until the vocabulary is final:
  - Add `<status>-surface-content` for all four statuses — `ALL` 41 → 45, `PAIRS` gains
    `<status>-surface => ['<status>-surface-content']` **replacing** the current
    `<status>-surface => ['<status>']`, and Daylight defines them as today's literal `X-800`
    (`red-800` `oklch(44.4% 0.177 26.899)`, `green-800` `oklch(44.8% 0.119 151.328)`,
    `yellow-800` `oklch(47.6% 0.114 61.907)`, `blue-800` `oklch(42.4% 0.199 265.638)`).
    `app.css`'s role-token block gains the four `var()` references.
  - Add `ThemeTokens::DECORATIVE = ['border']` and **remove `border` from `NON_TEXT`**.
- **`Oklch` needs a CSS-value parser** — `Oklch::fromCss()` accepting both `#rrggbb` and
  `oklch(96.7% 0.003 264.542)`, with `ColorContrast::resolve()` routed through it. Today
  `ColorContrast::ratio()` sends every string to `fromHex()`, which throws on the ~30 Daylight
  tokens stored as `oklch()`. This command needs it to print verdicts against existing tokens,
  and task 12's matrix cannot read config without it. Reuse `ThemeStyleBlock`'s accepted
  notation — the two must agree on what a valid value is.

Does **not** include: Dusk, or the final low-glare-dark. **Task 12 re-authors both** against the
settled vocabulary. Do not polish this one — it is a detector, and the vocabulary will change
under it as the sweep proceeds.

## Depends on

01, 02.

## Key decisions already made

- **This is an authoring command, not application code.** Nothing computes a ramp while serving
  a request; presets store final values. Do not put ramp logic in `app/Support` — spec 3
  promotes it if its live picker needs it.
- A ramp holds hue and chroma and steps lightness on a fixed curve. `--max-chroma` clamps
  accents (~0.12 for dark presets): saturated color at high lightness is what makes a dark
  theme painful.
- The command prints numbers because a human decides. Its output is pasted by hand.
- The rough preset is expected to be wrong. Its job is to make unswept elements glow light.

## Consult

`expanded/architecture.md` → *`theme:ramp`*; `expanded/data-model.md` → *Contrast thresholds*.

## Tests

`tests/Unit/OklchTest` — `fromCss()` round-trips both notations and rejects junk.
`ThemePresetTest` still green with 45 tokens (it already asserts every token in `ALL` appears
in `PAIRS`, so the four new ones are covered by construction).

`tests/Feature/Console/ThemeRampCommandTest` — assert properties, not a palette:
- Eleven shades keyed `50…950`, lightness strictly monotonic.
- Consecutive lightness deltas within tolerance of each other (perceptual evenness — the
  property the old eyeballed sRGB ramps failed).
- Hue preserved across the ramp.
- `--max-chroma` caps every shade and leaves an under-cap anchor untouched.

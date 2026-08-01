# 03 — `theme:ramp` command and a rough dark preset

## Scope

The authoring tool, plus a **deliberately provisional** second preset so the sweep tasks have
something to switch to.

- `app/Console/Commands/ThemeRampCommand.php` — `php artisan theme:ramp "#219ebc" --max-chroma=0.12`.
  Anchor in, eleven shades out as config-ready values, with each shade's contrast verdict
  printed beside it.
- `config/themes.php` — add a `low-glare-dark` preset from that output.

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

`tests/Feature/Console/ThemeRampCommandTest` — assert properties, not a palette:
- Eleven shades keyed `50…950`, lightness strictly monotonic.
- Consecutive lightness deltas within tolerance of each other (perceptual evenness — the
  property the old eyeballed sRGB ramps failed).
- Hue preserved across the ramp.
- `--max-chroma` caps every shade and leaves an under-cap anchor untouched.

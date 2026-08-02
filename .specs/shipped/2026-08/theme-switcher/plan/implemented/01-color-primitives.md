# 01 — Color primitives

## Scope

Two `app/Support` classes with real contracts, and their unit tests. **Nothing renders yet** —
no migration, no config, no Blade.

- `Oklch` — readonly value object (`l`, `c`, `h`).
  - `fromHex(string): self`, `toHex(): string`, `__toString(): 'oklch(0.62 0.11 220)'`
  - `withLightness(float): self`, `withChroma(float): self`
  - `relativeLuminance(): float`
- `ColorContrast` — `ratio(Oklch|string, Oklch|string): float` and
  `verdict(float $ratio, bool $isText, float $ceiling): Verdict` returning
  `too_low` / `ok` / `too_high`.

Does **not** include: ramp generation (task 03), the token list or presets (task 02).

## Depends on

Nothing.

## Key decisions already made

- **WCAG luminance is defined on sRGB.** Convert OKLCH → linear sRGB → sRGB properly. Do not
  approximate luminance with OKLCH `L`; they are different quantities and the ratios come out
  wrong in the midtones.
- Out-of-gamut colors clamp **chroma** down until in gamut, then convert. A naive per-channel
  clamp shifts hue. State the strategy in the class docblock.
- `verdict()` takes the ceiling as a parameter. No contrast constant lives in these classes —
  floors come from `config('themes.contrast')` at the callsite, ceilings from the preset.
- `Verdict` is an enum in `app/Enums`.

## Consult

`expanded/architecture.md` → *Support classes*; `expanded/data-model.md` → *Contrast
thresholds*.

## Tests

`tests/Unit/OklchTest`:
- `fromHex()` → `toHex()` round-trips within tolerance across the gamut.
- `relativeLuminance()` matches published WCAG figures for white (1.0), black (0.0), **and two
  midtones** — the extremes agree closely enough to hide the `L`-as-luminance bug, so the
  midtones are the actual test.
- Out-of-gamut input clamps rather than wrapping hue.

`tests/Unit/ColorContrastTest`:
- Known pairs against published ratios (white/black = 21:1, plus two real token pairs).
- `ratio()` is symmetric.
- `verdict()` returns each of the three outcomes, **and two different ceilings give different
  verdicts for the same ratio** — a test that hard-codes 15.0 re-freezes what per-preset
  ceilings exist to unfreeze.

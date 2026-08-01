# 12 — Final presets

Re-author both generated presets against the vocabulary as it actually ended up, and prove the
contrast rules across all three.

## Scope

- Re-generate **Dusk** and **Low-glare dark** with `theme:ramp` and replace task 03's rough
  values in `config/themes.php`.
- Declare each one's `contrast_ceiling` (Dusk ~12.0, Low-glare ~10.0).
- `tests/Feature/ThemeContrastTest` — the matrix.
- The full browser pass under all three presets.

## Depends on

11.

## Key decisions already made

- Task 03's dark preset was a detector and is expected to be wrong. Treat this as authoring from
  scratch, not tuning.
- **Low-glare dark is not inverted Daylight.** Dark grey surfaces, pale grey content,
  chroma-clamped accents (~0.12). White on black is 21:1 and is *worse* for astigmatism —
  halation makes light text bloom.
- Ceilings are per-preset and **warn**. Floors (4.5 text / 3.0 non-text) are global and reject.
  A preset may not declare a ceiling below the text floor; clamp it.
- Exactly three presets. A high-contrast fourth was considered and deferred.
- If the dark preset reveals a missing or collapsed token, **add the token** — do not fudge a
  value to hide it. That discovery is the entire reason the dark preset exists, and it goes in
  `resolution-log.md`.

## Consult

`expanded/data-model.md` → *Contrast thresholds*; `expanded/testing.md` → `ThemeContrastTest`.

## Tests

`tests/Feature/ThemeContrastTest`, data-provided over **every preset in
`config('themes.presets')` × every pair in `ThemeTokens::PAIRS`** — iterate config, not rows:
- ≥ 4.5 for text pairs; ≥ 3.0 for `accent`, `border`, `border-strong`, `focus`.
- ≤ that preset's ceiling, asserted **hard**. PHPUnit has no warning level, and the presets are
  ours — one breaching its own declared ceiling is a bug. "Warn, don't reject" governs spec 3's
  user input, not our fixtures.
- The four surfaces are **distinct values in the generated presets only**. Daylight cannot pass
  this — `x-card`, `x-table`, `x-dropdown` and `x-modal` are all `bg-white`, so pixel-stability
  forces `surface-raised == surface-overlay` there.

Browser pass: every page under each preset. Daylight is the computed-style diff from task 11.
Dusk and Low-glare are read by eye, looking for two things — a card that vanished into the page
(collapsed surfaces) and text that stayed dark on a dark surface (a missing `-content` pair).

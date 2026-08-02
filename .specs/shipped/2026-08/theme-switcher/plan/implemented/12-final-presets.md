# 12 — Final presets

Re-author both generated presets against the vocabulary as it actually ended up, and prove the
contrast rules across all three.

## Scope

- Re-generate **Dusk** and **Low-glare dark** with `theme:ramp` and replace task 03's rough
  values in `config/themes.php`.
- Declare each one's `contrast_ceiling` (Dusk ~12.0, Low-glare ~10.0) and **Daylight's, at 18.0**.
- **Re-author Daylight's six failing values** (below). This is the task that breaks
  pixel-stability, deliberately and only here.
- `tests/Feature/ThemeContrastTest` — the matrix, asserted across **all three presets**.
- The full browser pass under all three presets.

## Daylight's failures — measured, fix all six

The default theme fails 15 of 50 pairs as inherited. Four are `border` (now decorative, no
floor — see `00-overview.md`), five are duplicates of the same token across surfaces. The
distinct fixes:

| pair | today | why |
|---|---|---|
| `warning-content` on `warning` | **1.92** | white on `yellow-500`; the worst in the app |
| `success-content` on `success` | **3.29** | white on `green-600` |
| `content-subtle` on `surface*` | **2.36** | real body text — field hints, timestamps, "No event assigned" — not disabled controls, so no WCAG exemption |
| `link` on `surface` | **4.15** | near miss, app-wide |
| `content-muted` on `surface` | **4.39** | near miss, app-wide |
| `focus` on `surface` | **2.86** | focus indicator; 1.4.11 genuinely applies |

`primary-content` on `primary-active` reads 16.96 and fails as **TooHigh** only against the
15.0 default. Daylight's own 18.0 ceiling resolves it — do not lighten the navy.

> [!WARNING]
> `content-muted`, `content-subtle`, `link` and `focus` are four of the most-used tokens in the
> app. Changing them moves hundreds of usages at once. Do this as a deliberate authoring pass
> with the browser walk, not by nudging numbers until the test goes green.

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
- ≥ 4.5 for text pairs; ≥ 3.0 for `accent`, `border-strong`, `focus`.
- Tokens in `ThemeTokens::DECORATIVE` (`border`, `scrim`) are **skipped**, not floored —
  whether they appear as a pair's foreground or, in `scrim`'s case, have no pair at all. Assert
  the skip list is non-empty so the exemption stays visible.
- **`scrim` still needs a browser check under each preset**, since no assertion covers it: open
  a modal and confirm the page behind it is dimmed rather than washed out.
- ≤ that preset's ceiling, asserted **hard**. PHPUnit has no warning level, and the presets are
  ours — one breaching its own declared ceiling is a bug. "Warn, don't reject" governs spec 3's
  user input, not our fixtures.
- **No per-preset exemptions.** All three presets pass the same floors; that is what task 12's
  re-authoring buys. The one exception is surface-distinctness below.
- The four surfaces are **distinct values in the generated presets only**. Daylight cannot pass
  this — `x-card`, `x-table`, `x-dropdown` and `x-modal` are all `bg-white`, and re-authoring
  the elevation scale is out of scope here (it is the "regularize Daylight" follow-up, not a
  contrast fix).

Browser pass: every page under each preset. Daylight is the computed-style diff from task 11.
Dusk and Low-glare are read by eye, looking for two things — a card that vanished into the page
(collapsed surfaces) and text that stayed dark on a dark surface (a missing `-content` pair).

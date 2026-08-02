# 11 — Delete the legacy ramps and prove pixel-stability

The gate. Nothing renames after this; if something still referenced a ramp, the build breaks
here rather than in production.

## Scope

`resources/css/app.css`:
- Delete the five `--color-{ocean,aqua,navy,sun,flame}-*` ramps (55 declarations).
- Delete `--color-nav-active` and its placeholder comment.
- **Delete the `@layer base` border-color shim.** Its own comment names this spec as its
  remover; `--color-border` now carries the 88 width-only `border` usages.

`tests/Feature/NoHueNamedColorsTest` — allow-list emptied except `welcome.blade.php` (task 13).

## Depends on

10.

## Key decisions already made

- The shim restored v3's `gray-200` default border color against v4's `currentColor`. Removing
  it without `--color-border` in place turns 88 borders into text-colored hairlines — that is
  why this task comes last, not first.
- Daylight's values are unchanged from `master`, so **the computed-style diff must contain only
  the accepted differences already recorded in `resolution-log.md`** — seven classes as of task
  07. Read them from the log rather than this list; it grew from three during the sweep, and the
  log is the record:
  1. `x-badge` tint `X-100` → `X-50`
  2. `x-alert` border `X-200` → `<status>`
  3. `x-alert` icon `X-400` → `<status>`
  4. status buttons' focus rings → `focus`
  5. status buttons' hover/active → alpha on the fill (inverts the active step in Daylight)
  6. `x-sortable-header` hover-darken → underline, arrow at `table-header-content/70`
  7. `text-gray-800`/`text-gray-900` → `content` — **app-wide**, every heading near-black → navy-900
- Anything outside that list is a bug in this sweep. Contrast fixes are task 12's, not this
  task's: do not "correct" a color here.

## Consult

`expanded/architecture.md` → *CSS*; spec 1's `standing-issues.md` in
`.specs/shipped/2026-08/tailwind-4/` for what the shim was and why.

## Tests

- Full suite green: `composer test` and `npm run test`.
- `NoHueNamedColorsTest` passes with an allow-list of one path.
- **The regression gate, not a PHPUnit test**: diff computed styles for every element on the
  ~40 pages spec 1 walked, Daylight vs `master`, as spec 1 did. The criterion is task 05's
  three accepted differences and nothing else.
- Record every accepted difference in `resolution-log.md` with its reason, and carry the list
  into `standing-issues.md` when the feature ships.

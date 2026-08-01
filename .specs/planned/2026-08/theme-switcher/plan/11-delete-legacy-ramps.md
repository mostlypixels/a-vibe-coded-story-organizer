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
- Daylight's values are unchanged from `master`, so **the computed-style diff must be empty**.
  Any difference is either a bug in this sweep or a deliberate exception; there is no third
  kind.

## Consult

`expanded/architecture.md` → *CSS*; spec 1's `standing-issues.md` in
`.specs/shipped/2026-08/tailwind-4/` for what the shim was and why.

## Tests

- Full suite green: `composer test` and `npm run test`.
- `NoHueNamedColorsTest` passes with an allow-list of one path.
- **The regression gate, not a PHPUnit test**: diff computed styles for every element on the
  ~40 pages spec 1 walked, Daylight vs `master`, as spec 1 did. Empty diff is the criterion.
- Record every accepted difference in `resolution-log.md` with its reason, and carry the list
  into `standing-issues.md` when the feature ships.

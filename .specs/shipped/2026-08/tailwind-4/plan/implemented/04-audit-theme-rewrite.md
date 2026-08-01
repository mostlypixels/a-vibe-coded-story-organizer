# 04 — Audit the `theme()` rewrite

**Depends on:** 01, 02, 03.

## Scope

`resources/css/app.css` contained **86 `theme()` calls on 81 lines** before the codemod. Every
one must end as a correct `var(--…)` (or `calc(…)`) reference. Verify all 86 against the table
below, whatever the codemod did.

| v3 call | count | v4 | Note |
|---|---:|---|---|
| `theme('colors.X.Y')` | 41 | `var(--color-X-Y)` | direct |
| `theme('colors.white')` | 1 | `var(--color-white)` | direct |
| `theme('spacing.N')` | 24 | `calc(var(--spacing) * N)` | **not** `var(--spacing-N)` |
| `theme('spacing[0.5]')` | 3 | `calc(var(--spacing) * 0.5)` | same rule |
| `theme('fontSize.S')` | 8 | `var(--text-S)` | namespace renamed |
| `theme('borderRadius.md')` | 2 | `var(--radius-md)` | namespace renamed |
| `theme('borderRadius.full')` | 1 | `var(--radius-full)` | same |
| `theme('borderRadius.DEFAULT')` | 4 | **`var(--radius-sm)`** | see below |
| `theme('lineHeight.relaxed')` | 1 | `var(--leading-relaxed)` | namespace renamed |
| `theme('boxShadow.lg')` | 1 | `var(--shadow-lg)` | namespace renamed |

**Spacing is not a discrete scale in v4.** A single `--spacing: 0.25rem` multiplier derives
every step; `--spacing-1` does not exist. 27 of the 86 calls are affected — the largest group
after colours.

**`borderRadius.DEFAULT` is the trap.** v4 shifted the radius scale down a name: v3 `rounded`
(0.25rem) is v4 `rounded-sm`. There is no `--radius-DEFAULT`. Verified directly:
`--radius-sm: 0.25rem`.

## Why an audit and not just the guard

Task 02 catches dangling references. It does **not** catch a call rewritten to a
wrong-but-existing variable — `theme('spacing.2')` → `var(--text-sm)` resolves fine and passes
green. Those are found here, by reading, or not at all until task 09.

Note that v4 still resolves the old dot paths, so a `theme()` call the codemod *missed*
also builds green and produces correct output — it is simply not themeable. **Grep for
survivors explicitly**; the build will not tell you.

## Definition of done

- `grep -c "theme(" resources/css/app.css` returns **0**.
- Task 02's guard passes.
- Every `var(--…)` name in `app.css` appears in the built `:root`. Cross-check:
  ```bash
  grep -ohE 'var\(--[a-z0-9-]+' resources/css/app.css | sort -u
  ```
  against the `@layer theme` block in `public/build/assets/*.css`.

## Not in scope

- Changing any *value*. Colours keep their names and hex. 35 of the 42 colour calls are stock
  `gray`/`red`/`green`/`blue`/`purple`/`amber` in the revision-diff and callout rules; only 4
  touch `ocean` (`app.css:275, 276, 313, 564`). Spec 2 revisits all of them. This task changes
  syntax only.
- The border shim (05) and the nav token (06).

## Tests

No new test — task 02 is the guard. Run it, plus `npm run build`.

## Consult

`../expanded/architecture.md` — "The `theme()` rewrite" and its verification section.

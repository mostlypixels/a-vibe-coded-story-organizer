# Tailwind 4 — plan overview

The manual. Never implemented, never moved to `implemented/`.

Port the build from Tailwind 3.4.19 to Tailwind 4 with **no perceptible visual change**,
except two deliberate exceptions named below. No PHP behaviour changes; the only PHP edit in
the whole feature is one docblock.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `codemod-to-green-build` | Run `npx @tailwindcss/upgrade`, swap deps, wire the Vite plugin, delete both config files, land `@theme`. Ends with a build that succeeds. |
| 02 | `var-resolution-guard` | Vitest test asserting every `var(--…)` in the built CSS is also declared in it. Written *before* the rewrite audit so 04 has a machine check. |
| 03 | `audit-css-entry-point` | Verify `@import` / `@plugin` / `@source` / `@theme` by hand against what the codemod produced. |
| 04 | `audit-theme-rewrite` | The 86 `theme()` calls across 5 namespaces. Guarded by 02. |
| 05 | `border-color-shim` | Restore the v3 `gray-200` border default for 88 width-only usages. |
| 06 | `nav-active-token` | `--color-nav-active` placeholder + a real focus ring on the active nav link. |
| 07 | `remaining-guards` | Source-smoke test and config-files-gone test. |
| 08 | `docker-watcher-check` | Confirm the dev container still picks up newly-used utilities; correct the `vite.config.js` comment. |
| 09 | `browser-pass` | ~30 pages against `master`, twice. **This is the acceptance test.** |
| 10 | `documentation` | `standing-issues.md`, `documentation/*`, `CHANGELOG.md`. |

01 → 02 → 03 → 04 are strictly sequential. 05, 06, 07, 08 depend on 04 but not on each
other. 09 depends on everything before it. 10 depends on 09, because it records what 09 found.

## Binding decisions

Settled in the grill (2026-07-31). **Do not re-litigate these** — if one looks wrong while
implementing, stop and raise it in `resolution-log.md` rather than quietly choosing otherwise.

1. **Codemod first.** Task 01 accepts a large opaque diff in exchange for a fast green build.
   Tasks 03–04 exist precisely because the codemod is unreliable in known places.
2. **`tailwind.config.js` is deleted**, not kept via `@config`. Theme values must become
   runtime custom properties or `theme-switcher` has nothing to build on.
3. **`@theme`, never `@theme inline`.** `inline` substitutes values into utility rules instead
   of referencing custom properties; every runtime override in spec 2 would silently fail.
4. **The border default is restored by a base-layer shim**, not by editing 88 usages. The shim
   carries a comment at the rule saying what it restores and that spec 2 removes it.
5. **One nav token** (`--color-nav-active`), not two. The lost `focus:border-flame-600`
   distinction is replaced by `focus:ring-2`, matching the rest of the app.
6. **Browser floor accepted**: Safari 16.4+ / Chrome 111+ / Firefox 128+. Documented, not
   enforced at runtime.
7. **`@tailwindcss/forms` 0.5.11 and `@tailwindcss/typography` 0.5.20 stay as-is.** Verified
   current (forms published 2026-05-12; its peer range names v4 explicitly). No version bump,
   no strategy change — form controls are covered by the browser pass instead.
8. **Accepted differences go in `standing-issues.md`** in this feature folder.

## Invariants every task must preserve

- **No PHP behaviour changes.** One docblock edit in `AppearanceController` is the entire
  permitted PHP diff. If a task finds itself editing a controller, request, policy or model,
  it has gone wrong.
- **No route, no migration, no model, no policy.** This feature adds none.
- **Colour names and values do not change.** `ocean`, `aqua`, `navy`, `sun`, `flame` keep their
  names and their hex values. Renaming to role tokens is `theme-switcher`, spec 2.
- **Only two intended visual changes**, both recorded in `standing-issues.md`:
  the `flame` → `fuchsia` nav placeholder, and the active nav link's new focus ring.
  Everything else that looks different is a bug.
- **A green pipeline proves nothing here.** `composer test` renders no CSS; `npm run build`
  succeeds on a stylesheet with silently-dropped declarations. Never report a task verified on
  the strength of the suite alone when the task changed CSS.

## Verified facts

Established by probing Tailwind 4.3.3 directly, not from documentation. Rely on these:

- v4 **still resolves** old `theme()` dot paths (`borderRadius.DEFAULT`, `spacing[0.5]`,
  `fontSize.sm`, `lineHeight.relaxed`, `boxShadow.lg`), inlining them as literals. This is why
  01 can produce a green build with the rewrite still pending.
- v4 **tracks `var(--…)` references in hand-written CSS** and keeps those theme variables
  emitted, so the rewrite will not dangle through tree-shaking. Only a *misspelling* dangles.
- A dangling `var()` **compiles clean and silent** — no warning, no error, no failing test. The
  browser drops the declaration at compute time. This is the single reason task 02 exists.
- `--radius-sm: 0.25rem` in v4 equals v3's `borderRadius.DEFAULT`.

## Reference

Detail lives in `../expanded/`: `overview.md` (goals, acceptance), `architecture.md` (pipeline,
the `theme()` mapping table, `@source`), `ui.md` (drift counts, page inventory),
`testing.md` (the three guards, the manual procedure), `open-questions.md` (decisions, with
the reasoning and the counter-arguments that were heard and overruled).

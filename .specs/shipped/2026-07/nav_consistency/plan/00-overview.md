# Nav consistency — plan overview

> This file is the manual. It is never itself implemented or moved to `implemented/`.
> Read it, then execute the numbered task files in order.

## What we're building

Highlight the active section/page in the **desktop** primary-nav dropdowns
(`resources/views/layouts/navigation.blade.php`), consistently across **Timeline**,
**Codex**, and **Story**, plus their collapsed **trigger** buttons. Purely a Blade /
presentation change — no routes, controllers, policies, models, or migrations.

Root cause the plan addresses: `x-dropdown-link` has no `active` prop (unlike `x-nav-link` /
`x-responsive-nav-link`), so desktop dropdown items can't highlight. We add the prop, then
wire every desktop dropdown and its trigger, driving them from one consolidated set of
route-match booleans.

## Execution order

| # | Task | Purpose |
| --- | --- | --- |
| 01 | `dropdown-link-active-and-story` | Add optional `active` state to `x-dropdown-link`; introduce the shared `@php` matcher block; wire the **Story** dropdown items + trigger; point the responsive Story links at the same booleans. First verifiable slice. |
| 02 | `codex-and-timeline-dropdowns` | Extend the matcher block and wire the **Codex** (types + Attributes) and **Timeline** (Plotlines + Events) dropdown items + triggers; re-point their responsive links too. |
| 03 | `docs-and-changelog` | `CHANGELOG.md` entry + `documentation/` nav note. |

## Binding design decisions (do not re-litigate in tasks)

1. **Scope = all three desktop dropdowns** (Story, Codex, Timeline) — not Story-only.
2. **Trigger highlighting is in scope** — the collapsed trigger reflects when any child route
   is active, using `x-nav-link`'s active look (`text-white border-b-2 border-flame-500`).
3. **Item active style** = `bg-aqua-50 text-navy-900 font-semibold` (light-panel palette from
   `responsive-nav-link`), **no** left accent bar. Non-active branch stays byte-identical to
   today's `x-dropdown-link` class string.
4. **`aria-current="page"`** on the active item `<a>` — active state is never color-only.
5. **One source of truth for matching:** named boolean vars in a single `@php` block at the top
   of the shared `@if ($project = …)`, reused by desktop triggers, desktop items, **and** the
   responsive menu. No `Nav` support class, no view composer — that's disallowed new
   architecture for a styling tweak.
6. **`active` prop defaults to `false`** so untouched call sites (Settings dropdown) are
   unaffected.
7. Reuse existing color tokens only (`navy-*`, `aqua-*`, `flame-*`); introduce no new Tailwind
   colors.

## Invariants every task must preserve

- **No behavior change to routing/auth.** Nav only renders section links when a `$project`
  resolves from the route (`navigation.blade.php:15-22`); keep that guard intact.
- **Existing `x-dropdown-link` call sites unchanged** until explicitly wired — verified by the
  non-active class string being identical to today.
- **Exactly one section active at a time.** Story/Scenes/Acts/Chapters and Codex namespaces are
  distinct; matchers must not overlap (guard with a "non-active sibling" assertion).
- **Matchers reuse the exact expressions already in the responsive menu** — do not invent new,
  divergent `routeIs(...)` globs.

## Reference

Detail lives in `../expanded/`: `architecture.md` (component + wiring + DRY), `ui.md`
(styling + a11y), `testing.md` (assert on `aria-current`, not Tailwind classes),
`open-questions.md` (all resolved — see decisions above).

# 10 — Documentation and standing issues

**Depends on:** 09 (it records what the browser pass found).

## Scope

### `standing-issues.md` — new, in the feature folder

Facts still true of `master` after this ships. Follow the shape of
`.specs/shipped/2026-07/revision-history-rework/standing-issues.md`. Seed with:

- **The border-colour shim.** Restores v3's `gray-200` default rather than editing 88 usages.
  Contradicts v4's own default. `theme-switcher` removes it when `--color-border` exists.
  Accepted cost, not an oversight.
- **The active nav link's focus ring.** v3 had only a border darken (`focus:border-flame-600`)
  and `outline-none`; collapsing to one nav token would have left no focus affordance, so a
  `focus:ring-2` was added. A deliberate, small improvement over the previous behaviour.
- **The nav indicator's contrast.** `fuchsia` (like the `flame` it replaced) is under the 3:1
  non-text minimum on white. Known, deferred to spec 2, which picks the real colour.
- **Anything task 09 accepted rather than fixed.**
- **The Docker follow-up**, if task 08 found polling redundant but did not remove it.

Each entry names the decision to re-open, so disagreeing with one is possible without
archaeology.

### `documentation/architecture.md`

- Build pipeline: PostCSS is gone; `@tailwindcss/vite` is the whole chain.
- **Theme tokens are now runtime CSS custom properties** — say why it matters, since this is
  the hook `theme-switcher` hangs off. One or two sentences, not a section.
- Browser floor: Safari 16.4+ / Chrome 111+ / Firefox 128+, documented not enforced, with the
  reason (v4 needs `@property`, `color-mix()`, cascade layers).

### `documentation/ui-components.md`

It states colours are written as full Tailwind class strings because of static extraction —
still true, and now worth naming auto-detection as the mechanism, since the `content` array it
implicitly referenced no longer exists.

Correct any documented default this port moved (`shadow-sm`, `rounded`, ring widths).

### `documentation/dependency-overrides.md`

Check whether `postcss` or `autoprefixer` appear; remove them if so.

### `AppearanceController` docblock — the only PHP edit in the feature

It currently reads *"This placeholder page is the final v1 form — no later task enriches it."*
`display-configurator` is exactly that later task. Correct the sentence to point at it.

Do not touch the controller's behaviour or its view.

### `CHANGELOG.md`

A dated section per the project convention, **without** the `(#PR)` suffix — `pr-land.sh`
stamps that. Group under `Changed`:

- Build moved to Tailwind 4; PostCSS and autoprefixer removed
- Active nav link now shows a focus ring
- Browser floor: Safari 16.4+ / Chrome 111+ / Firefox 128+

## Not in scope

- A `documentation/theming.md` deep dive — nothing to describe yet. Spec 2 writes it.
- Documenting the token vocabulary — spec 2.

## Tests

`composer test`, `npm run test`, `composer lint`. The lint run matters here: it is the last
chance to catch PHP that crept in beyond the one docblock.

## Consult

`../expanded/architecture.md` — "Documentation to update"; `.specs/README.md` for the
`standing-issues.md` convention.

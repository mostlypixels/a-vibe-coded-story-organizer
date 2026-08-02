# 10 — Sweep: hand-written CSS and JS

The usages a Blade-only sweep leaves dangling. Small, and easy to forget entirely.

## Scope

**`resources/css/app.css`** — 42 hue-variable references *outside* `@theme`:

| Block | References |
|---|---|
| `.tiptap` placeholder + tables | `gray-400/300/50`, `ocean-50` |
| `.wysiwyg-slash` menu | `gray-200/700/400`, `ocean-100/800` |
| `blockquote[data-callout-type]` × 5 | `blue`, `green`, `purple`, `amber`, `red` — 500 + 700 each |
| `.revision-diff*` | ~20: `green-100/400/700`, `red-100/400/700`, `blue-100/300/500/800`, `gray-50/200/300/600/700/900` |

**`resources/js/autosave/badge.js`** — hard-codes `border-gray-300 bg-white text-gray-600` in
two places (a state map and a default).

**`tests/Feature/NoHueNamedColorsTest`'s scan pattern is too narrow** — found during task 09.
It matches only `ocean|aqua|navy|sun|flame|gray|slate`, so every built-in Tailwind status hue
(`red`, `green`, `blue`, `yellow`, `amber`, `emerald`, `indigo`, `purple`, `orange`, …) passes
straight through. That guts the guard exactly where the sweep is subtlest — status colors are
the ones with four tokens and the easiest to get wrong — and it means task 11's "allow-list is
empty" gate proves less than it looks.

Widen `PATTERN` to cover the built-in hues, and do it **in this task**, before task 11 leans on
it. A scan with the widened pattern currently reports violations in exactly one file —
`resources/js/autosave/badge.js`, already in scope above — so widening it costs nothing beyond
this task's own work. Watch two false-friend cases: `neutral` is a *token* name (`bg-neutral`)
as well as a Tailwind ramp, and the shade-digit requirement is what keeps them apart, so keep
the `-(50|[1-9]00|950)` suffix mandatory rather than matching a bare hue word.

## Depends on

09.

## Key decisions already made

- **This is where the tinted status tokens earn their existence.** `danger-surface`,
  `success-surface`, `warning-surface`, `info-surface` have no Blade caller at all — the callout
  blockquotes and the diff rules are their only users. If one turns out unused after this task,
  delete the token.
- `.diff-note`'s comment says it is kept in step with `x-badge`'s `info` variant. That coupling
  is real: task 05 renamed the badge, so this rule moves to match. Keep the comment accurate.
- The five callout types are `blue`/`green`/`purple`/`amber`/`red`. Four map onto
  `info`/`success`/`warning`/`danger`; **`purple` has no status equivalent** — map it to
  `accent-surface`/`accent-content` (the pair task 05 created for the badge) rather than adding
  a sixth status.
- `badge.test.js` asserts the exact class strings. **Move the vitest fixture in the same
  commit**, and remember `npm run test` is a separate command from `composer test` — a green
  PHP suite proves nothing here.

## Consult

`expanded/architecture.md` → *`app.css`'s own hand-written rules*; `expanded/ui.md` sweep table.

## Tests

- `npm run test` green, with `badge.test.js` updated.
- `RichTextRenderingTest`, `DiffComponentTest`, `RevisionCompareTest` stay green.
- `NoHueNamedColorsTest` allow-list now contains only `welcome.blade.php`.
- Render a document with all five callout types and a revision diff, under both presets.

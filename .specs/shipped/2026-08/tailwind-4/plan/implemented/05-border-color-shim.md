# 05 — Border colour shim

**Depends on:** 04.

## Scope

v4's width-only `border` defaults to `currentColor`; v3 defaulted to `gray-200`. **88 exact
usages** across `resources/views` and `resources/js` rely on the old default, plus 2
`divide-*`. Restore it with one base rule rather than 88 edits.

```css
@layer base {
  *, ::after, ::before, ::backdrop, ::file-selector-button {
    border-color: var(--color-gray-200);
  }
}
```

## The comment is part of the deliverable

The rule must explain itself where a reader finds it — not only in a spec folder. It has to
say what it restores, why it exists, and that it is temporary. Something like:

```css
/*
 * Tailwind 3 defaulted every border to gray-200; Tailwind 4 defaults to
 * currentColor, which would turn 88 width-only `border` usages into
 * text-coloured hairlines. This restores the v3 behaviour so the port
 * changes nothing visually.
 *
 * Deliberately temporary: the theme-switcher spec introduces a
 * --color-border token and rewrites those usages, and removes this block
 * in the same pass. See .specs/…/tailwind-4/standing-issues.md.
 */
```

This was the explicit condition attached to choosing the shim — the counter-argument (invisible
magic in a codebase written for junior developers) was heard and accepted on the strength of
this comment plus the standing-issues entry.

## Key decisions

- **Shim, not 88 explicit `border-gray-200` edits.** Doing 88 edits inside a PR whose
  acceptance criterion is "nothing changed" is 88 chances to change something.
- The shim contradicts v4's own default for as long as spec 2 takes to land. That is the
  accepted cost.

## Not in scope

- Editing any of the 88 usages.
- Introducing `--color-border` — spec 2 owns that.
- Writing the `standing-issues.md` entry — task 10 creates that file. Note it in
  `resolution-log.md` so 10 does not have to rediscover it.

## Tests

None new. `npm run build`, task 02's guard, `composer test`.

Sanity check by eye on one page with a bordered card: the hairline should be the same light
grey as on `master`, not the text colour.

## Consult

`../expanded/ui.md` — "The `border` decision"; `../expanded/open-questions.md` §1.

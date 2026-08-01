# 07 — Remaining regression guards

**Depends on:** 04, 06 (the source-smoke test asserts the nav-active utility exists).

## Scope

Two tests. Both are cheap, both run in CI, both catch a failure that is otherwise invisible
until someone opens a specific page.

### 1. Source-smoke test (vitest, alongside task 02's)

Assert the built CSS contains rules for classes that can only be present if the scanner reached
a source it must reach:

| Class | Proves |
|---|---|
| a pagination class from `vendor/…/Pagination` | the `@source` directive works |
| `.prose` | `@tailwindcss/typography` loaded via `@plugin` |
| a `@tailwindcss/forms` reset selector | the forms plugin loaded |
| `.border-nav-active` | `@theme` custom colours emit utilities |

Pick the pagination class by reading the actual vendor Blade template rather than guessing —
and use one that is *distinctive*, not something like `flex` that any page would produce.

Without this, the pagination regression ships silently.

### 2. Config-files-gone test (PHPUnit unit test)

Assert `tailwind.config.js` and `postcss.config.js` do not exist, in the spirit of
`tests/Unit/SpecsStatusConsistencyTest`. Insurance against a future `npx` run or a merge
resurrecting them, which would quietly reintroduce a second source of truth for the theme.

Keep it in `tests/Unit/`; it needs no database and no `RefreshDatabase`.

## Not in scope

- The `var()` guard — task 02, already done.
- Visual regression snapshots. Genuinely useful, genuinely a larger project than this
  migration, and they would have to be maintained through spec 2's rename, which *intends* to
  change every colour. Explicitly rejected; do not add them.

## Tests

These tasks are the tests. Verify each fails for the right reason before it passes:
temporarily remove the `@source` line and confirm the smoke test goes red; temporarily
`touch postcss.config.js` and confirm the unit test goes red. Revert both.

## Consult

`../expanded/testing.md` — guards #2 and #3.

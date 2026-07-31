# 02 — `var()` resolution guard

**Depends on:** 01 (needs a build to run against).

## Scope

One vitest test that fails when the built stylesheet references a custom property it never
declares. This is the only mechanical check that exists for the whole migration.

- New file alongside the existing co-located tests, e.g. `resources/js/css-build.test.js`
  (match whatever naming the existing `resources/js/*.test.js` files use).
- Read the build output — `public/build/assets/*.css`, resolved by glob, not a hardcoded
  hashed filename.
- Collect every `var(--x)` **reference** and every `--x:` **declaration**.
- Fail on `references - declarations`, listing the offenders.
- Skip gracefully with a clear message when no build output exists, so a bare `npm run test`
  on a fresh clone does not fail for the wrong reason.

## Why it exists

Probed against Tailwind 4.3.3: a dangling `var(--radius-DEFAULT)` compiles clean, emits no
warning, and passes every existing check. The browser silently drops the declaration at
compute time. Without this test, a wrong `theme()` rewrite in task 04 ships invisibly and is
discovered — if at all — by someone noticing a square corner months later.

## Not in scope

- The source-smoke test and the config-files-gone test — task 07.
- Actually fixing anything the test reports — task 04.

## Known limitation, state it in the test's own comment

This catches *dangling* references only. A call rewritten to a wrong-but-existing variable —
`theme('spacing.2')` becoming `var(--text-sm)` — resolves fine and passes. That class of error
is task 04's manual audit and task 09's browser pass. The test is a floor, not a ceiling; say
so in the file so nobody later mistakes it for full coverage.

## Tests

This task *is* the test. Verify it works by temporarily introducing a dangling `var(--nope)`
into `app.css`, confirming a red run, then removing it. Do not commit the deliberate break.

## Consult

`../expanded/testing.md` — guard #1.

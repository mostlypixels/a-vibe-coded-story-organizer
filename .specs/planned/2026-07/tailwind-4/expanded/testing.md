# Testing — Tailwind 4

## The honest position

`composer test` renders no CSS. `npm run test` (vitest, `resources/js/*.test.js`) tests JS
behaviour, not styling. `npm run build` succeeds on a stylesheet full of dangling `var()`
references, because CSS drops unresolvable declarations silently.

**No existing test can fail as a result of this migration.** A green suite is not evidence
the port worked — it is evidence the port did not break PHP. Say so in the PR rather than
letting a green check imply more than it does.

The project's usual rule (every change ships with a feature test; a bug fix adds a test that
fails first) does not apply here, because there is no endpoint, controller action or bug. The
substitutes below are what stand in for it.

## Regression guards worth adding

These are cheap, they run in CI, and each catches a failure mode that is otherwise invisible
until someone looks at a page.

### 1. Every `var()` in `app.css` resolves — the highest-value check

The `theme()` rewrite's failure mode is a variable that does not exist (`--radius-DEFAULT`,
`--spacing-1`, `--text-sm` misspelled as `--font-size-sm`). Build, then assert every custom
property referenced by the emitted stylesheet is also declared in it.

- Implement as a **vitest** test over `public/build/assets/*.css` — it is a build artifact,
  not a PHP concern, and vitest already runs in CI after `npm run build`.
- Parse `var(--x)` references and `--x:` declarations; fail on the set difference.
- Guard: skip gracefully when no build output exists, so a bare `npm run test` locally does
  not fail for the wrong reason.

This one test replaces most of the manual care demanded by `architecture.md`'s mapping table.

### 2. The build produces the utilities the app uses

A smoke assertion that the emitted CSS contains rules for a handful of classes proven to come
from a source the scanner must reach:

| Class | Proves |
|---|---|
| a pagination class from `vendor/…/Pagination` | the `@source` directive works |
| `.prose` | `@tailwindcss/typography` loaded via `@plugin` |
| a `@tailwindcss/forms` reset selector | forms plugin loaded |
| `.border-nav-active` (or whatever the placeholder token is called) | `@theme` custom colours emit utilities |

Without this, the pagination regression ships silently — it is one page, and it is the page
nobody opens.

### 3. Config files are gone

A trivial PHP unit test asserting `tailwind.config.js` and `postcss.config.js` do not exist,
in the spirit of `tests/Unit/SpecsStatusConsistencyTest`. Cheap insurance against a future
`npx` run or a merge resurrecting them, which would silently reintroduce a second source of
truth for the theme.

> [!NOTE]
> Resist going further. Visual-regression snapshots (Playwright + image diff) would genuinely
> catch drift, but standing that up is a larger project than this migration and would have to
> be maintained through spec 2's rename, which *intends* to change every colour. Not now.

## Manual verification — the actual acceptance test

The browser pass in `ui.md` is the test. Structure it so it produces evidence:

1. Check out `master`, `npm run build`, screenshot every page in the inventory.
2. Check out the branch, `npm run build`, screenshot the same pages at the same viewport.
3. Diff pairwise. Every difference is either fixed or written down.

Record the outcome in the PR description and, for anything accepted rather than fixed, in
`standing-issues.md` — that file is the convention for "still true of the code" (see
`.specs/shipped/2026-07/revision-history-rework/standing-issues.md`).

Expected and *not* drift:

- The `flame` → `fuchsia` nav indicator (deliberate placeholder)
- Slight saturation shift on stock palette colours on P3-capable displays (OKLCH)

## Suite hygiene

- Run `composer test` and `npm run test` before opening the PR, per `CLAUDE.md`.
- `composer lint` touches PHP only; it will not see any of this. Still run it — the port
  should not have touched PHP at all, and a lint diff means something unintended crept in.
- CI already runs `npm ci && npm run build`, so a hard build failure is caught. Nothing else
  in CI looks at the stylesheet.

## Docker

`documentation/docker.md` describes `make test` / `make lint` / `make shell` + `npm run build`
as the same commands inside a container. Verify the build once inside Docker before merging:
the Vite plugin replaces the PostCSS pipeline, and the dev container's polling watcher
(`VITE_USE_POLLING`, 60s interval in `vite.config.js`) is the one piece of the setup that
interacts with how Tailwind now watches files. See `open-questions.md`.

---
title: Autosave With Revisions — Testing
---

# Testing

Per `handoff.md` §9.12 and CLAUDE.md's testing conventions (`RefreshDatabase`, model
factories, `actingAs($user)`, `route()` helper, `composer test` via in-memory SQLite +
paratest — no shared state across processes, time-based assertions use `travel()` not
real sleeps).

## PHPUnit — server contract

New `tests/Feature/FieldAutosaveTest.php` (one file covering the generic endpoint across
a representative field or two, not one file per model — the endpoint is generic):

* Happy path: PATCH updates the live column and creates a revision.
* Non-owner gets 403 (mirrors every other controller test in this codebase per
  CLAUDE.md's authorization rule).
* Unknown `{entity}` slug 404s at the router (not the controller) — assert via a slug
  not in `AutosavableFields::slugs()`.
* Validation failure returns 422 with the same rule the Form Request would enforce (cap,
  `ValidMarkdown`, `SanitizeHtml`).
* 409 on a stale `base_hash`; 200 on a correct one.
* Coalescing: two automatic saves inside the configured window update one row (assert
  `revisions` count unchanged, `value` changed); a save after `travel($window + 1)`
  seconds inserts a new row.
* Byte-identical save writes no revision at all (§2.2).
* `manual=true` (form submit) always inserts a fresh row tagged `origin: manual`, even
  inside what would otherwise be a coalescing window.
* `run_matcher=true` on a `Scene.contents` save triggers `SceneReferenceMatcher::
  syncScene()` (assert the pivot changes); a bare debounce PATCH does not.
* 429 with `Retry-After` when `throttle:120,1` trips.
* Baseline seeding: a field's first-ever revision is preceded by a `baseline` row
  carrying the pre-edit value and `created_at = updated_at`, not `now()`.

`tests/Feature/RevisionTest.php`:

* History index lists revisions oldest/newest as expected, label search filters
  correctly, list query never selects `value` (assert via query log or a dedicated
  "value column absent from response" check).
* Revert: creates a **new** revision with `origin: revert`, doesn't touch/delete any
  prior row, and the live column reflects the reverted value.
* Compare: word-level diff for a rich field two versions differing only in wrapper tags
  reports "formatting changed only"; a real prose change reports the actual diff.
* Every §4.2 pruning safety rule as its own test: `model:prune` never removes a labeled
  revision, never removes a non-automatic-origin revision, never removes the newest
  revision of a field even when it's the only one and is old.
* `RevisionPurger` categories (automatic/manual/labeled/imported, and age cutoffs) each
  get a test; the artisan command and the settings-panel controller action both route
  through it (assert by mocking/spying the service, or by asserting identical row counts
  removed via each entry point).
* Backfill migration: run against a seeded pre-existing row with data in every
  registered field, assert one `baseline` revision per non-empty field, `size_bytes`
  populated.
* `RevisionSetting` confirm-gate: lowering `retention_days` returns the confirmation
  count computed from the real prunable query (seed rows that would/wouldn't be pruned
  at the new value and assert the count matches exactly); raising it skips confirmation.

## Vitest — client decision logic

New `resources/js/autosave/store.test.js` (co-located, matching the convention `expand-
tip-tap`'s `open-questions.md` already settled: co-located `*.test.js`, confirmed live
in `package.json`'s `"test": "vitest run"`). No DOM, no browser — pure function/state
tests:

* State transitions cover every pair in the precedence table (`architecture.md`):
  `session-expired > conflict > error > retrying > saving > saved > idle`.
* Status-code mapping: 401/419 → `session-expired` (never `error`); 409 → `conflict`;
  429 → `retrying` with `Retry-After` honored; network failure → `retrying` with
  backoff.
* `localStorage` draft-triage rule (§9.7's three-way table): server-matching value drops
  silently; matching base hash offers Restore/Discard; mismatched base hash offers
  Compare/Discard only, never a bare Restore.
* Retry backoff timing (deterministic, fake timers — no real sleeps, matching the
  paratest "no real sleeps" convention extended to JS).

## Manual checklist (`run-imagoldfish` skill)

For the cases vitest/PHPUnit can't reach:

* A real expired session in a live browser tab (not a mocked 419).
* `localStorage` quota exhaustion behavior (eviction, not a crash).
* Visual check of the global badge's fixed position not colliding with `x-modal`.

## CLAUDE.md update required

Per `handoff.md`'s note: adding `npm run test` as a canonical command already happened
in `expand-tip-tap` (confirmed: CLAUDE.md's Commands section already lists `npm run
test`) — no further CLAUDE.md change needed here, just continuing to use the existing
co-located convention.

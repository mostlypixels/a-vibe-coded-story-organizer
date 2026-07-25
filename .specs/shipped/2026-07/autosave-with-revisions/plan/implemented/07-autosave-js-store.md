# Task 7 — `resources/js/autosave/store.js` state machine

## Scope

A plain JS module, no DOM/Alpine/axios dependency — pure functions and a small state
object — implementing:

* The state enum: `idle | saving | saved | retrying | conflict | session-expired |
  forbidden-after-replay | error`, and the precedence order for the global badge
  (`session-expired > conflict > forbidden-after-replay > error > retrying > saving >
  saved > idle` — inserting the new state directly after `conflict` since both represent
  "your last save definitely did not land and needs a human decision", ahead of the
  softer `error`/`retrying` states).
* `mapResponse(status, headers)` → next state, per the table in
  `expanded/architecture.md` (200 → saved; 401/419 → session-expired; 409 → conflict;
  422 → error with field errors; 429 → retrying, honoring `Retry-After`; network failure
  → retrying with backoff).
* Retry backoff timing (deterministic — parameterized so tests can inject fake timers,
  no real `setTimeout` waits in the test suite itself).
* The `localStorage` draft-triage decision (`handoff.md` §9.7's three-way rule): given a
  stored `{value, baseHash, savedAt}` and the current server `{value, hash}`, return one
  of `drop-silently | offer-restore | offer-compare-only`.
* The `forbidden-after-replay` transition: entered specifically when a queued replay
  (after a `session-expired` recovery) returns 403 — distinct from a 403 encountered on
  a first, non-replayed save (which is a plain authorization error and should not exist
  in practice, since the UI never lets an unauthorized user open the field to begin
  with, but the mapping function should still be explicit about the distinction so the
  Alpine adapter in task 8 doesn't have to guess).

Does **not** include: any DOM manipulation, any `fetch`/`axios` call, the Alpine
component (task 8), or the retry *queue* itself (a list of pending saves) beyond the
timing/backoff function — task 8's adapter owns actually holding and replaying the
queue, this module only decides *when* and *what state to be in*.

## Depends on

Task 6 (fixes the real response shape/status codes this module's tests assert against).

## Key decisions already made

* **This module has no side effects.** Every function is a pure transformation:
  `(state, event) => newState` or `(input) => decision`. That's what makes it
  vitest-testable with no browser (`handoff.md` §9.12 — "no browser, no DOM").
* **401 and 419 collapse into the same `session-expired` state** — indistinguishable
  from the writer's chair (`handoff.md` §9.6).
* **429 never becomes `error`** — always `retrying`, honoring `Retry-After` when
  present, falling back to the module's own backoff schedule otherwise.
* **The `localStorage` triage never returns a bare "restore" when the base hash doesn't
  match the current server value** — only `offer-compare-only` in that case (never
  silently offer to clobber newer server data).

## Consult

* `expanded/ui.md` — "Autosave client module" section.
* `expanded/architecture.md` — the status-code mapping table and precedence order.
* `handoff.md` §3.4, §9.6, §9.7, §9.12.

## Tests

`resources/js/autosave/store.test.js` (co-located, per this project's vitest
convention — confirmed in `package.json`):

* Every entry in the status-code mapping table, one test each.
* Precedence ordering: given a set of simultaneous per-field states, the computed
  global-badge state is always the worst one per the documented order — table-driven
  test over multiple combinations, including the new `forbidden-after-replay` state's
  position.
* Retry backoff: assert the delay sequence (e.g. exponential or whatever schedule is
  chosen) using fake timers — no real waits.
* `localStorage` triage, one test per row of `handoff.md` §9.7's table: server-matching
  value → `drop-silently`; matching base hash → `offer-restore`; mismatched base hash →
  `offer-compare-only`.
* `forbidden-after-replay` is only reachable via the "403 after a session-expired
  recovery" path, not via a bare first-attempt 403 (the mapping function takes a
  "was this a replay" flag explicitly — test both branches).

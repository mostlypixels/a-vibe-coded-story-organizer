# Testing & verification

This feature ships no application code, so there is no Laravel feature/unit test in the usual
sense (`RefreshDatabase`, factories, `actingAs`). Verification is a mix of one lightweight
tracked assertion, `.gitignore` behavior checks, and manual protocol walk-throughs.

## What CAN be asserted automatically

A small plain-filesystem unit test (same style as `tests/Unit/SpecsStatusConsistencyTest` —
`PHPUnit\Framework\TestCase`, no DB, runs under `composer test`) can guard the checked-in
artifact and the ignore rule. Candidate `tests/Unit/ToolingConventionsTest`:

1. **Conventions file exists** — `.claude/conventions/tooling.md` is present.
2. **`CLAUDE.md` references it** — `CLAUDE.md` contains the string
   `.claude/conventions/tooling.md` (the one-line pointer is wired).
3. **No OS is privileged** — the conventions file does **not** contain a "prefer PowerShell"
   style phrase (assert absence of `/prefer PowerShell/i`). This locks in G1.
4. **Ignore pattern present** — `.gitignore` contains the line `.claude/env.*.local.md`.
5. **No env cache is tracked** — glob `.claude/env.*.local.md` and assert none is committed
   (defensive: a copied cache should never enter version control).

> [!NOTE]
> Keep this test filesystem-only and cheap, like `SpecsStatusConsistencyTest`. It guards the
> *durable, checked-in* half of the feature; the machine-local cache and the runtime protocol
> are behavioral and are verified manually below.

## What must be verified manually (behavioral)

The cache lifecycle and the copy-safety logic are Claude-workflow behaviors, not code paths, so
verify them by walking the protocol:

* **Fresh machine, no cache.** Remove any `.claude/env.*.local.md`, start a session, confirm
  Claude computes the machine id, picks the shell by availability, probes on demand, and writes
  a stamped cache. Filename `<id8>` must equal the in-file `id`.
* **Warm cache hit.** With a valid cache present, confirm Claude uses `test: composer test` and
  `pnpm: unavailable` *without* re-probing.
* **Negative-fact thrash stop.** Confirm `pnpm: unavailable` actually prevents repeated pnpm
  probes within the trust window.
* **Copied-repo / cloned-VM case (the critical one).** Hand-edit a cache's in-file stamp to a
  different `machine`/`id` (simulating a file that arrived by copy), then confirm Claude detects
  the mismatch, ignores the foreign file, and regenerates. This is G3 — the whole reason the
  in-file stamp exists.
* **Positive-fact invalidation.** Make a cached command fail (e.g. cache a stale `serve`
  command) and confirm Claude drops and re-detects rather than looping on the stale value.
* **Toolchain-changed reset.** After "I just installed pnpm," confirm the negative `pnpm`
  fact is cleared and pnpm is re-detected.

## Edge cases to cover in the walk-throughs

* Hostname contains characters awkward in a filename → confirm the id8 still disambiguates and
  the filename is filesystem-safe.
* Two machines share a hostname but differ in machine-id → filenames differ via `<id8>`; no
  collision.
* Machine-id source unavailable (locked-down registry / no `/etc/machine-id`) → define the
  fallback behavior (see `open-questions.md`).

## This spec's own filing (meta)

`SpecsStatusConsistencyTest` will assert that once this feature moves to `expanded/`, its
`spec.md` frontmatter reads `status: expanded`. The expander stamps and moves in one step, so
after the move `composer test` must stay green — a quick `composer test` run after shipping the
expansion confirms the status/folder agreement.

# Open questions — RESOLVED (decided 2026-07-10)

All six were confirmed with their recommended answers. Summary of the binding decisions:

| Q | Decision |
|---|---|
| Q1 | Keep the `CLAUDE.md` env block as machine-specific fact; move the *rule* to `tooling.md` and add the one-line pointer. Do not delete the env block. |
| Q2 | Machine-id fallback = hash `hostname` alone, stamp `id: <hash> (hostname-fallback)`; never block on a missing id. |
| Q3 | Include the negative `assertDoesNotMatch('/prefer PowerShell/i', ...)` guard in the test. |
| Q4 | Trust window is **qualitative**: positive facts drop on command failure, negative facts clear when the user says the toolchain changed. No numeric TTL. `detected_on` is human-legible only. |
| Q5 | Ship the small filesystem `ToolingConventionsTest`; cache behavior stays manually verified. |
| Q6 | Cache stays in-repo under `.claude/` (gitignored) + in-file stamp; accept the copy risk. |

The original recommendations and rationale are retained below for context.

---

Each question states a recommended answer (now the accepted answer above).

## Q1 — Reconcile the platform-neutral rule with the current `CLAUDE.md` shell line

`CLAUDE.md`'s environment block says "PowerShell (primary); Bash tool also available." That
reads OS-preferential, which G1 removes.

**Recommendation:** Treat the environment block as *machine-specific fact* (this checkout is on
Windows, so PowerShell is primary *here*) and keep it, but move the *rule* to `tooling.md`
phrased by availability. Add the one-line pointer so the durable rule wins. Do **not** delete
the environment block — it is auto-populated context, not a convention.

**Decide:** keep the env block as-is + pointer (recommended), or also soften its wording?

## Q2 — Machine-id fallback when the OS source is unavailable

Locked-down Windows registry, missing `/etc/machine-id`, etc.

**Recommendation:** Fall back to hashing `hostname` alone for `<id8>` and record
`id: <hash> (hostname-fallback)` in the stamp, so the copy-detection still functions (weaker,
but non-fatal). Never block on a missing machine id — the cache is an optimization, not a
gate.

**Decide:** hostname-hash fallback (recommended), or skip caching entirely when id is
unavailable?

## Q3 — Should the automated test assert absence of "prefer PowerShell"?

A negative-string assertion (`assertDoesNotMatch('/prefer PowerShell/i', ...)`) locks in G1 but
is brittle if wording drifts.

**Recommendation:** Yes, include it — it is the single cheapest guard against the exact
regression that motivated this spec, and rewording to keep it passing is trivial. Pair it with
the positive assertions (file exists, `CLAUDE.md` references it, ignore pattern present).

**Decide:** include the negative assertion (recommended) or rely on review only?

## Q4 — Trust-window durations: concrete or qualitative?

The spec says positive facts hold "until failure" and negative facts carry "a shorter window."

**Recommendation:** Keep it **qualitative**, not a numeric TTL — Claude has no reliable session
clock, and the real invalidation signals are *command failure* (positive) and *user says
toolchain changed* (negative). Encode those two triggers as the rule; do not invent a
"7-day" number. The `detected_on` date is for human legibility, not an automated expiry.

**Decide:** qualitative triggers (recommended) or a dated TTL?

## Q5 — Ship the automated `ToolingConventionsTest`, or docs-only?

The feature is Claude-tooling; arguably it needs no PHP test.

**Recommendation:** Ship the small filesystem test. It is cheap, matches the existing
`SpecsStatusConsistencyTest` precedent, and turns three acceptance criteria (file exists,
referenced, ignore pattern) into CI guards for near-zero cost. The behavioral cache logic stays
manual.

**Decide:** ship the test (recommended) or keep it documentation + manual verification only?

## Q6 — Does the cache belong in `.claude/` or somewhere hostname-scoped outside the repo?

Keeping it in `.claude/` (gitignored) is what the spec says, but it means a copied repo
physically carries the file (mitigated by the in-file stamp).

**Recommendation:** Keep it in `.claude/` per spec — the in-file stamp is the deliberate answer
to the copy problem, and an in-repo location is discoverable and self-documenting. Moving it to
a user-home cache dir would dodge the copy entirely but scatter tooling outside the repo and
complicate discovery. Accept the copy risk; rely on the stamp.

**Decide:** in-repo gitignored + stamp (recommended), or user-home cache dir?

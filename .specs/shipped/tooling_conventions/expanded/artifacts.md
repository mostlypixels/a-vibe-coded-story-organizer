# Artifacts — the two files this feature produces

This feature has no database and no models; its "data model" is two Markdown files. This
document specifies each file's location, whether it is tracked, its structure, and its
invariants. (Named `artifacts.md` rather than `data-model.md` because there is no schema.)

## Artifact A — `.claude/conventions/tooling.md` (checked in, travels with the repo)

**Location:** `.claude/conventions/tooling.md` (new `conventions/` subfolder under `.claude/`).
**Tracked:** yes — committed, shared by every checkout.
**Referenced by:** exactly one line added to `CLAUDE.md` (see `architecture.md` for wording and
placement). The reference is one line so the rule set stays single-sourced; do not inline the
rules into `CLAUDE.md`.

### Required sections

1. **Native shell by tool availability, not by name.** Use whichever shell tool the environment
   exposes — PowerShell where available (typically Windows), otherwise Bash/POSIX (typically
   Linux/macOS). No shell is privileged. This *replaces* any "prefer PowerShell, fall back to
   Bash" wording.
2. **Never carry one shell's syntax into the other's tool.** No PowerShell here-strings /
   `&&` chains / `$VAR` in the Bash tool, and vice-versa. This is the platform-independent rule
   that prevents the class of bug that motivated the audit — call that out explicitly.
3. **Prefer dedicated file/search tools over any shell** (Read / Edit / Grep / Glob),
   regardless of platform — sidesteps `\` vs `/` and quoting entirely. Restates the existing
   `CLAUDE.md` guidance so it lives with the rest of the tooling rules.
4. **The lockfile decides the package manager — never guess:**
   npm ⇽ `package-lock.json`, pnpm ⇽ `pnpm-lock.yaml`, yarn ⇽ `yarn.lock`, PHP ⇽ `composer.lock`.
5. **Canonical commands, defined once:** test = `composer test` (per `CLAUDE.md`); build / lint /
   serve likewise. Resolved values live in the cache (Artifact B), not scattered across prose.
6. **Cache protocol:** consult the local env cache before probing; append after learning;
   re-verify on failure. Cross-reference Artifact B.
7. **Machine-id computation, verbatim** (the per-OS commands from `open-questions.md` / spec §
   "How to compute the machine id"), so any machine can reproduce the cache filename.

### Invariants

* The file names **no single OS as preferred.** A reviewer grepping for "prefer PowerShell"
  must find nothing.
* Every rule is phrased as a decision procedure keyed on an observable (a lockfile, a tool
  probe result), not on `$OS`.

## Artifact B — `.claude/env.<hostname>-<machineid8>.local.md` (gitignored, machine-local)

**Location / filename:** `.claude/env.<hostname>-<machineid8>.local.md`, where `<machineid8>` is
the first 8 hex chars of the hashed OS machine id (see `architecture.md`).
**Tracked:** no — matched by the new `.gitignore` pattern `.claude/env.*.local.md`.
**Lifecycle:** created lazily the first time Claude probes anything on a machine with no valid
local cache; appended to as facts are learned; regenerated when the stamp is foreign or the user
declares the toolchain changed.

### Header (the self-stamp — the correctness guarantee)

```
machine: DESKTOP-AB12 · id: 3f9c1a7b · detected_on: 2026-07-10
```

* `machine` — live hostname.
* `id` — the short (8-hex) machine id; **same value as in the filename**.
* `detected_on` — date the cache was first stamped.

The **filename** is the fast lookup; the **in-file stamp** is the correctness check for the
cloned-VM edge case where hostname, machine-id, *and* the copied file all coincide — see the
verification rule in `architecture.md`.

### Body (learned facts, one per line, each dated)

```
shell:    powershell    (2026-07-10)
composer: available 2.x (2026-07-10)
npm:      available      (2026-07-10)
pnpm:     unavailable    (2026-07-10)
test:     composer test
serve:    php artisan serve
```

### Invariants

* **Negative facts are first-class.** `pnpm: unavailable` must be recorded — omitting negatives
  is what leaves the thrash in place.
* **Every fact is dated** so the trust window (below) can be applied.
* **Trust window:**
  * *Positive* facts hold until a command relying on them fails; on failure, drop the fact and
    re-detect.
  * *Negative* facts carry a **shorter** window and are cleared when the user says the toolchain
    changed — otherwise a newly installed tool would never be retried.
* The file is **never committed.** If `git status` ever shows it, the ignore pattern is wrong.

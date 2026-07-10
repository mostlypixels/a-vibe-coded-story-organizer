# Overview — Portable tooling conventions + reactive machine-local env cache

## Problem statement

Claude re-derives the same toolchain facts every session ("try all the things") and the
project's shell guidance reads Windows-first, even though the repo is pulled — or *copied* —
onto other machines. Two concrete failure modes:

1. **Windows-biased shell guidance.** Rules phrased as "prefer PowerShell, fall back to Bash"
   privilege one OS. On a Linux/macOS checkout, Bash is native and PowerShell usually is not
   installed, so the guidance points at the wrong tool.
2. **Probe thrash.** Claude spends turns re-detecting the package manager, test command, and
   which binaries exist, and repeats probes it already failed earlier in the session (or a
   prior one). Nothing caches the *negative* result ("pnpm is not installed"), which is the
   fact that actually stops the retrying.

A naive machine-local cache fixes (2) but introduces a new bug: when the repo is **copied**
(zip / rsync / cloned VM image) rather than **pulled**, `.gitignore` no longer guards the
cache, so one machine's facts ride along onto another and are trusted as if local.

## Goals

* **G1 — Platform-neutral rules.** A single checked-in convention file that selects the shell
  by *tool availability*, never by OS name, and that forbids cross-pollinating one shell's
  syntax into the other's tool.
* **G2 — Learn-by-doing cache.** A gitignored, machine-local file that records tool outcomes
  (positive *and negative*) as Claude actually runs them, consulted before probing.
* **G3 — Copy-safe identity.** The cache self-identifies to the machine that wrote it, so a
  file that arrives by copy on a foreign machine is detected and ignored rather than trusted.
* **G4 — Zero app impact.** No application code, migrations, routes, or runtime behavior
  change. This is Claude-workflow tooling only.

## Non-goals

* **A SessionStart hook** that auto-regenerates the cache. Ship file-only first; add the hook
  (via `update-config`) later, only if manual refresh proves annoying. (Out of scope per spec.)
* **Replacing `fewer-permission-prompts`.** Permission-prompt thrash is that skill's job; this
  cache complements it.
* **A pre-scan / inventory step.** The cache is explicitly reactive — it must not front-load a
  detection sweep at session start (that is the very thrash being removed).
* **Any change to `composer test`, CI, or the app's behavior.**

## User stories

* *As Claude on a Windows checkout*, I read the cache, see `shell: powershell` and
  `pnpm: unavailable`, and skip the probes I would otherwise run.
* *As Claude on a fresh Linux clone*, I find no cache, use the platform-neutral rules to pick
  Bash, and write a new cache stamped to this machine as I learn.
* *As Claude on a machine that received the repo as a **copy*** (the previous machine's cache
  rode along), I read the in-file stamp, see it names a different machine, ignore the foreign
  cache, and regenerate — no stale facts are trusted.
* *As a developer who just installed pnpm*, I tell Claude the toolchain changed; the negative
  `pnpm: unavailable` fact is cleared and pnpm is re-detected on next use.

## Acceptance criteria

1. `.claude/conventions/tooling.md` exists, is checked in, and is referenced by exactly one
   line from `CLAUDE.md`.
2. The conventions file selects the shell by availability (no "prefer PowerShell" phrasing) and
   states the never-cross-syntax rule and the lockfile-decides-package-manager rule.
3. `.gitignore` ignores `.claude/env.*.local.md`; a generated cache file is untracked.
4. A cache file carries a header stamping `hostname`, short `machine-id`, and `detected_on`,
   and its filename is `env.<hostname>-<machineid8>.local.md`.
5. The conventions file documents the read protocol: **verify stamp → consult → probe only on
   miss → append outcome → re-verify on failure**, and the per-OS machine-id computation
   verbatim.
6. A cache whose in-file stamp does not match the live machine is treated as foreign and
   ignored (the copied-repo case).
7. No file under `app/`, `routes/`, `database/`, or `tests/` that affects app behavior changes.

## Relationship to existing conventions

This layers onto, and must stay consistent with, existing project rules already in `CLAUDE.md`
and `.claude/guidelines.md`:

* "Prefer the dedicated file/search tools over shell commands when one fits" — the conventions
  file restates this as the platform-independent default.
* "test = `composer test`" — becomes the canonical, defined-once test command the cache
  resolves rather than re-derives.
* The `.specs/` lifecycle and `SpecsStatusConsistencyTest` govern *this* spec's own filing; see
  `testing.md` for how the expand→ship moves keep status and folder in agreement.

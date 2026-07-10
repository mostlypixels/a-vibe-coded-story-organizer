---
status: shipped
shipped: 2026-07-10
---

# Portable tooling conventions

> [!NOTE]
> **Scope as shipped is narrower than originally specced.** This feature began as a larger
> design — portable rules **plus** a gitignored, self-stamped machine-local env cache and a PHP
> SessionStart hook. The cache and hook were built and then removed as over-built for the payoff;
> only the portable rule set survives. This `spec.md` describes what actually exists. The full
> arc — what was explored, why it was dropped, and how to recover it — is in
> [`resolution-log.md`](resolution-log.md), which is the source of truth. The discarded cache/hook
> code remains recoverable from git history and the `archive/tooling-conventions` tag.

## Problem

* Shell guidance phrased as "prefer PowerShell, fall back to Bash" privileged Windows. On a
  Linux/macOS checkout, Bash is native and PowerShell usually isn't installed, so the guidance
  pointed at the wrong tool.
* Nothing prevented one shell's syntax leaking into the other's tool (a PowerShell here-string in
  the Bash tool, `&&` chains in the PowerShell tool) — the class of bug that motivated the audit.
* Package-manager and workflow-command choices were guessed rather than read from the repo.

## What shipped

A single checked-in, platform-independent rule set at **`.claude/conventions/tooling.md`**,
referenced by one pointer line in `CLAUDE.md` and summarised for developers in
`documentation/best-practices.md`. It travels with the repo and reads the same on any OS. Its five
rules:

1. **Native shell by tool availability, not by name** — no OS is privileged; use whichever shell
   tool the environment exposes.
2. **Never carry one shell's syntax into the other's tool** — the platform-independent rule that
   prevents the cross-shell bug class.
3. **Prefer dedicated file/search tools** (Read/Edit/Grep/Glob) over any shell, on every OS.
4. **The lockfile decides the package manager** — never guess.
5. **Canonical commands, defined once** (test = `composer test`).

This is Claude-workflow tooling only — no application code, routes, migrations, or runtime
behaviour changed.

## Explored and dropped

The machine-local env cache (`.claude/env.*.local.md`) and its portable PHP SessionStart hook
(read/verify/inject, fail-open) were implemented, then removed: the cache leaned on Claude
reliably *appending* learned facts — the least reliable link — and in practice sat empty right
after install, adding a class, a hook, tests, settings wiring, and dev-only autoload for little
payoff. See `resolution-log.md` → *Feedback & decisions* for the full reasoning; the code is in
git history (shipped in commit `d273397`) and under the `archive/tooling-conventions` tag if the
idea is ever revisited.

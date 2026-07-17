---
name: extract-tools-and-commands
description: Recurring audit of this project's .claude/ skills and agents that extracts qualifying inlined command sequences into bash scripts (scripts/) or artisan commands, rewires the callers, and keeps the tool index up to date. Use when asked to extract tools/commands from skills, harden a skill into scripts, or run a tooling extraction pass.
---

# Extracting tools and commands

A recurring maintenance pass over **this project's** skills (`.claude/skills/`) and agents
(`.claude/agents/`). Inlined command sequences that meet the criteria below get extracted
into standalone tools, and the calling skill is rewired to invoke the tool instead of
spelling out the steps. Claude Code core/plugin skills are out of scope — only skills that
live in this repo.

Run it periodically, or whenever new skills have been added (written or downloaded) since
the last pass. It pairs with `session-retro`: retro proposes new tooling, this skill
hardens existing tooling into scripts.

## Extraction criteria

Extract a sequence only when **at least one** of these holds:

1. **Reused** — the same sequence appears in ≥2 skills/agents, or in a skill *and* a human
   workflow.
2. **Fragile** — easy to get subtly wrong (ordering, flags, cleanup), so a script encodes
   the correctness once. Example: a branch → push → auto-merge ritual.
3. **Token-heavy** — several agent round-trips of output parsing that one script call with
   a clean exit code would collapse.

Do **not** extract: one-liners, sequences whose steps need judgment between them (that is
the skill's job), or single-caller sequences with no fragility.

## Which kind of tool

* **Artisan command** — when the logic needs the Laravel app (Eloquent, config, seeded
  data) **or** the tool is human-facing and interactive. Lives in `app/Console/Commands`
  under a shared namespace (e.g. `spec:draft`), ships with a feature test, and uses
  `PromptsForMissingInput` so a human gets prompted for omitted arguments while an agent
  can pass them all non-interactively.
* **Bash script in `scripts/`** — for agent/CI plumbing: git rituals, scaffolding under
  `.specs/` or `.claude/`, CI helpers. Bash scripts are for the agent; artisan commands
  are for the developer.

## Bash script contract

Every script in `scripts/` must:

* Take inputs as **positional arguments/flags only** — never interactive prompts (agents
  run non-interactive shells; a prompt hangs the call). Missing argument → one-line usage
  message and non-zero exit.
* Resolve paths from the repo root via `git rev-parse --show-toplevel` — no full system
  paths, works from any cwd.
* Read secrets from the **environment only**, never as arguments (arguments land in shell
  history and transcripts); error clearly if unset. No personal information baked in.
* Start with `set -euo pipefail`.
* Carry a header comment: what it does and which skill(s)/agent(s) call it — the
  back-reference that keeps script and skill from drifting apart.
* Be plain POSIX-leaning bash. **One shell, no OS forks**: Git Bash is present on every
  contributor machine (it ships with Git) and CI runs Linux. Do not create per-OS
  variants or subfolders.

## Procedure

1. **Audit** — scan `.claude/skills/*/SKILL.md` and `.claude/agents/*.md` for inlined
   command sequences. Check `scripts/README.md` and `php artisan list` first so you don't
   propose something that already exists.
2. **Propose** — present a candidate table: sequence, where it appears, which criterion it
   meets, proposed tool name and kind (bash vs artisan). Wait for the user to approve the
   candidates. Extract **only approved ones**.
3. **Extract** — for each approved candidate: create the script/command, **rewire every
   calling skill/agent** to invoke it instead of the inlined steps (a script nobody calls
   is dead weight; a skill that still inlines the steps is instant drift), and smoke-test
   the tool once for real.
4. **Index** — update `scripts/README.md` (one line per script: name, purpose, callers).
   Artisan commands need no index; `php artisan list` is the index.
5. **Ship** — via the `ship-pr` skill (branch → PR → auto-merge).

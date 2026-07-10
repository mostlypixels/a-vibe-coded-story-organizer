# Portable tooling conventions & the env cache

This page explains the *developer-tooling* layer that helps Claude (and you) pick the right
shell, package manager, and workflow commands on **any** machine — Windows, Linux, or macOS —
without guessing. It is **Claude-workflow tooling only**: it touches nothing under `app/`,
`routes/`, or `database/`, and changes no runtime application behaviour. It lives under
`.claude/`.

If you never edit `.claude/`, you can safely ignore all of this. Read on when you touch the
conventions file, the hook, or wonder why a `env.*.local.md` file shows up in your checkout.

## The three pieces

| Piece | File | Role |
| --- | --- | --- |
| Conventions | [`.claude/conventions/tooling.md`](../.claude/conventions/tooling.md) | The checked-in, **portable** rule set: how to choose a shell/package manager/command. Reads the same on every OS. |
| Env cache | `.claude/env.<host>-<id8>.local.md` | A **machine-local, gitignored** note of what was actually detected on *this* machine, learned by doing. Never committed. |
| Hook | [`.claude/hooks/session-start.php`](../.claude/hooks/session-start.php) + `EnvCache.php` | A thin PHP SessionStart hook that reads/verifies the cache and injects it into context. Fail-open. |

### 1. Conventions (checked in, portable)

The rules select tools by **what the environment exposes**, never by the operating system's
name. Key points a junior dev should internalise:

- **No shell is privileged.** Use whichever shell tool the environment gives you (PowerShell
  where present, POSIX `sh` otherwise). Decide by observing what is available, not by guessing
  from the OS.
- **Never carry one shell's syntax into the other's tool.** The Bash tool runs POSIX `sh`; the
  PowerShell tool runs PowerShell. Keep each command wholly inside one dialect — this is the
  platform-independent rule that motivated the whole feature.
- **The lockfile decides the package manager** (`composer.lock` → composer, `package-lock.json`
  → npm, `pnpm-lock.yaml` → pnpm, `yarn.lock` → yarn). Never guess.
- **Canonical commands are defined once** (for this project, test = `composer test`).

### 2. The env cache (machine-local, never committed)

The *resolved* facts for the current machine — which shell is native here, which package
managers are installed, the exact `serve` invocation that works — live in a single Markdown
file per machine, `env.<hostname>-<machineid8>.local.md`. It is:

- **Gitignored** via the `.claude/env.*.local.md` pattern, so it is *never* committed. If
  `git status` ever shows one, the ignore pattern is broken.
- **Copy-safe.** The filename embeds an 8-hex machine id, and the file's first line re-stamps
  `machine`/`id`. If a checkout is zipped/rsynced/cloned to another machine, the in-file stamp
  no longer matches the live host, so the cache is treated as **foreign** and regenerated —
  filename match alone is not trusted.
- **Learned by doing.** Facts (positive *and* negative, e.g. `pnpm: unavailable`) are appended
  as they are discovered, never pre-scanned. A positive fact drops when a command relying on it
  fails; a negative fact clears when you say the toolchain changed. No numeric TTL.

> [!WARNING]
> Do not commit an `env.*.local.md` file, and do not copy one between machines expecting it to
> apply. Each machine grows its own.

### 3. The SessionStart hook (thin, fail-open)

`php .claude/hooks/session-start.php` runs when a Claude Code session starts (registered in the
tracked `.claude/settings.json`). It only ever **reads, verifies, and injects** — it never runs
a tool-detection sweep:

- Prunes foreign-stamped caches and creates a *header-only* stamp for this machine when none
  exists.
- Injects the current cache body into context so already-learned facts are reused without
  re-probing.
- Is **strictly fail-open**: any error exits 0 with no output, and Claude falls back to the
  portable rules plus reactive probing. If `php` is not on `PATH`, the session still starts;
  nothing is injected.

The copy-detection logic is unit-tested in `EnvCache` (`.claude/hooks/EnvCache.php`, wired via
`composer.json`'s **`autoload-dev`** — dev-only, never the shipped app autoload).

## What the tests guard

Two unit tests run under `composer test`:

- `tests/Unit/ToolingConventionsTest` — filesystem-only, mirrors `SpecsStatusConsistencyTest`:
  the conventions file exists, `CLAUDE.md` points at it, the file privileges no OS (asserts the
  absence of "prefer PowerShell"), the `.gitignore` pattern is present, and no `env.*.local.md`
  is tracked in git.
- `tests/Unit/EnvCacheTest` — exercises the machine-id derivation, the self-stamp, and the
  foreign-cache detection.

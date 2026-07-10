# Toolchain & shell conventions (portable)

These rules are **platform-independent**. They select tools by *what the environment exposes*,
never by the name of the operating system. They travel with the repo, so they must read the same
whether the checkout lives on Windows, Linux, or macOS. Machine-specific facts (which shell is
native here, which package managers are installed) do **not** belong in this file — they live in
the machine-local env cache (see *Cache protocol* below).

## 1. Native shell by tool availability, not by name

Use whichever shell tool the environment actually exposes. In this harness that is PowerShell
where it is available (typically Windows) and Bash/POSIX otherwise (typically Linux/macOS). **No
shell is privileged** — do not assume one OS. Decide by probing/observing which shell tool is
present, not by guessing from `$OS` or a hostname.

> This replaces any older OS-biased "prefer one shell, fall back to the other" wording. There is
> no default shell; there is only the shell this environment gives you.

## 2. Never carry one shell's syntax into the other's tool

**This is the platform-independent rule that prevents the class of bug that motivated this audit.**
The Bash tool runs POSIX `sh`; the PowerShell tool runs PowerShell. Their syntax does not
interchange, and mixing them fails in confusing, environment-dependent ways.

Do not put PowerShell constructs into the Bash tool, and do not put POSIX constructs into the
PowerShell tool. Concretely:

* **In the Bash tool:** no PowerShell here-strings (`@'…'@` / `@"…"@`), no `Verb-Noun` cmdlets,
  no `$env:VAR`, no backtick line-continuation, no `HKLM:\…` PSDrive paths. Use POSIX: `$VAR`,
  `/dev/null`, forward slashes, `cmd1 && cmd2`, heredocs (`<<'EOF'`).
* **In the PowerShell tool:** no POSIX-only constructs where PowerShell differs. Remember that
  `&&`/`||` and ternary/null-coalescing are unavailable in Windows PowerShell 5.1 (`A; if ($?){B}`),
  `2>/dev/null` becomes `2>$null`, and `head`/`tail`/`which`/`touch` do not exist as commands.

When in doubt, keep each command wholly inside one shell's dialect. Never author a single line that
would only parse under the *other* shell's tool.

## 3. Prefer dedicated file/search tools over any shell

Regardless of platform, prefer the dedicated tools over shell commands whenever one fits:

* **Read** a file instead of `cat`/`Get-Content`.
* **Edit** / **Write** instead of `sed`/`Set-Content`/`echo >`.
* **Grep** (ripgrep) instead of `grep`/`Select-String`.
* **Glob** instead of `find`/`Get-ChildItem -Recurse`.

This sidesteps `\` vs `/` path separators and shell quoting entirely, and it is the portable
default across every OS. (This restates the existing `CLAUDE.md` guidance so it lives with the
rest of the tooling rules.)

## 4. The lockfile decides the package manager — never guess

Read the lockfile present in the repo; do not infer the package manager from prose, habit, or OS:

| Lockfile present | Package manager |
|---|---|
| `package-lock.json` | **npm** |
| `pnpm-lock.yaml` | **pnpm** |
| `yarn.lock` | **yarn** |
| `composer.lock` | **composer** (PHP) |

If the expected lockfile is absent, that tool is not the project's package manager here — do not
run it speculatively.

## 5. Canonical commands, defined once

Define each workflow command in exactly one place and refer to it by name everywhere else. For
this project:

* **test** = `composer test`
* **build**, **lint**, **serve** = the project's documented equivalents (see `CLAUDE.md`'s
  *Commands* / *Testing* sections and `package.json` / `composer.json` scripts).

The *resolved* values for the current machine (e.g. the exact `serve` invocation that works here)
live in the env cache (below), not scattered across prose. Change the canonical command in one
place and every caller follows.

## 6. Cache protocol — consult before probing, append after learning

A machine-local, gitignored env cache records tool facts as they are learned, so probes are not
repeated every session. The file is
`.claude/env.<hostname>-<machineid8>.local.md` (see the *Machine-id computation* recipe below for
`<machineid8>`). Its format — header stamp plus dated positive **and negative** facts — is
documented alongside the `.gitignore` pattern for it.

Follow this state machine before spending a turn probing the toolchain:

1. **Compute the machine id** and build the expected filename `env.<host>-<id8>.local.md`.
2. **File missing?** No cache. Use the portable rules above, probe on demand, and create the
   cache (with a header stamp) as you learn facts.
3. **File present?** Read its header stamp. If the in-file `machine` / `id` do **not** match the
   live host and re-derived id, treat the file as **foreign** (it arrived by copy — zip, rsync,
   cloned VM) — ignore it and regenerate. Filename match alone is **not** sufficient; the in-file
   stamp is the correctness check.
4. **Stamp matches?** If the needed fact is present and still trusted, use it and **skip the
   probe**. Otherwise probe **once**, then append the outcome — recording negatives
   (`pnpm: unavailable`) as well as positives — each fact dated.
5. **Later, a command relying on a cached fact fails?** Drop that fact, re-detect, and update the
   cache.

**Trust window (qualitative, no numeric TTL):** positive facts hold until a command relying on
them fails; negative facts are cleared when the user says the toolchain changed (so a newly
installed tool is retried). Never pre-scan or front-load a detection sweep — the cache is
reactive, learned by doing.

### Env cache format

The cache is a single Markdown file, gitignored by the `.claude/env.*.local.md` pattern, so it is
**never committed** — if `git status` ever shows one, the ignore pattern is wrong.

* **Filename:** `env.<hostname>-<machineid8>.local.md`, where `<machineid8>` is the first 8 hex
  chars of the hashed OS machine id (see section 7). The filename is the O(1) lookup.
* **Header stamp** (first line — the correctness check for the copied/cloned-machine case):

  ```
  machine: <host> · id: <id8> · detected_on: <date>
  ```

  `machine` is the live hostname, `id` is the same 8-hex value as in the filename, and
  `detected_on` is the human-legible date the cache was first stamped (no TTL is derived from it).
  If the in-file `machine`/`id` do not match the live host and re-derived id, the file is
  **foreign** — ignore it and regenerate. Filename match alone is not sufficient.
* **Body** — one dated fact per line, e.g.:

  ```
  shell:    powershell    (2026-07-10)
  composer: available 2.x (2026-07-10)
  npm:      available      (2026-07-10)
  pnpm:     unavailable    (2026-07-10)
  test:     composer test
  serve:    php artisan serve
  ```

  **Negative facts are first-class** — record `pnpm: unavailable` rather than omitting it; leaving
  negatives out is what leaves the every-session probe thrash in place. Every learned fact is dated
  so the trust window above can be applied. Positive facts drop when a command relying on them
  fails; negative facts clear only when the user says the toolchain changed. No numeric TTL.

## 7. Machine-id computation (verbatim, per OS)

The cache filename embeds the first **8 hex chars** of the hashed OS machine id, so any machine
can reproduce and locate its own cache file. Read the OS machine identifier, hash it, and keep the
first 8 hex characters.

* **Windows (PowerShell):**
  ```powershell
  (Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid
  ```
* **Linux:**
  ```bash
  cat /etc/machine-id    # fallback: cat /var/lib/dbus/machine-id
  ```
* **macOS:**
  ```bash
  ioreg -rd1 -c IOPlatformExpertDevice | awk -F'"' '/IOPlatformUUID/{print $4}'
  ```

Hash to the short id (Bash):

```bash
printf '%s' "<id>" | sha256sum | cut -c1-8
```

PowerShell equivalent: hash the id stream with `Get-FileHash` or
`[System.Security.Cryptography.SHA256]` and keep the first 8 hex chars.

Computing the machine id is **one cheap probe per session** — the one probe always worth running,
because it names the cache file. If the OS source is unavailable, fall back to hashing the
`hostname` and note the fallback in the stamp.

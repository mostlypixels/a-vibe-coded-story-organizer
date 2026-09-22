# Toolchain & shell conventions (portable)

These rules are **platform-independent**. They select tools by *what the environment exposes*,
never by the name of the operating system. They travel with the repo, so they must read the same
whether the checkout lives on Windows, Linux, or macOS.

## 1. Native shell by tool availability, not by name

Use whichever shell tool the environment actually exposes. In this harness that is PowerShell
where it is available (typically Windows) and Bash/POSIX otherwise (typically Linux/macOS). **No
shell is privileged** — do not assume one OS. Decide by probing/observing which shell tool is
present, not by guessing from `$OS` or a hostname.

## 2. Never carry one shell's syntax into the other's tool

The Bash tool runs POSIX `sh`; the PowerShell tool runs PowerShell. Their syntax does not
interchange, and mixing them fails in confusing, environment-dependent ways.

Do not put PowerShell constructs into the Bash tool, and do not put POSIX constructs into the
PowerShell tool. Concretely:

* **In the Bash tool:** no PowerShell here-strings (`@'…'@` / `@"…"@`), no `Verb-Noun` cmdlets,
  no `$env:VAR`, no backtick line-continuation, no `HKLM:\…` PSDrive paths. Use POSIX: `$VAR`,
  `/dev/null`, forward slashes, `cmd1 && cmd2`, heredocs (`<<'EOF'`).
* **In the PowerShell tool:** PowerShell 7 syntax. `&&` and `||` chain commands, but
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
default across every OS.

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

Discover the exact invocation that works on the current machine when you need it, rather than
scattering machine-specific values across prose. Change the canonical command in one place and
every caller follows.

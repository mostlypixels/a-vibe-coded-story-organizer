---
status: draft
---

# Portable tooling conventions + a reactive, machine-local env cache

Claude re-probes the same toolchain facts every session ("try all the things") and its
shell guidance currently reads Windows-first, even though the repo is pulled — or copied
— to other machines. This spec defines two artifacts that fix both: a **checked-in,
portable rule set** and a **gitignored, self-stamped cache of facts learned by doing**.

No code or config is written by this spec — it only records the design so a later pass
can implement it.

## Problem

* Shell guidance phrased as "prefer PowerShell, fall back to Bash" privileges Windows.
  On a Linux/macOS stack, Bash is native and PowerShell usually isn't installed.
* Claude wastes turns re-detecting which package manager, test command, and binaries
  exist, and repeats failed probes it already ran earlier.
* A machine-local cache is the fix, but a naive one breaks when the repo is **copied**
  (zip / rsync / cloned drive) rather than **pulled** — gitignore only guards the pull
  path, so a copy carries one machine's facts onto another.

## Part 1 — Portable rules (checked in, travel with the repo)

Home: `.claude/conventions/tooling.md`, referenced by one line from `CLAUDE.md`.

* **Native shell by tool availability, not by name.** Use whichever shell tool the
  environment exposes: PowerShell where available (typically Windows), otherwise
  Bash/POSIX (typically Linux/macOS). No shell is privileged.
* **Never carry one shell's syntax into the other's tool** (e.g. no PowerShell
  here-strings in the Bash tool, no `&&` chains / `$VAR` in the PowerShell tool). This
  is the platform-independent rule that prevents the class of bug that motivated the
  audit.
* **Prefer the dedicated file/search tools** (Read/Edit/Grep/Glob) over any shell,
  regardless of platform — it sidesteps `\` vs `/` and quoting entirely.
* **The lockfile decides the package manager** — never guess (npm ⇽ `package-lock.json`,
  pnpm ⇽ `pnpm-lock.yaml`, yarn ⇽ `yarn.lock`; PHP ⇽ `composer.lock`).
* **Canonical commands are defined once** (test = `composer test` per CLAUDE.md; build /
  lint / serve likewise) — resolved values live in the local cache, not scattered.
* **Consult the local env cache before probing; append to it after learning; re-verify
  on failure.** (See Part 2.)

## Part 2 — Reactive, machine-local env cache (gitignored, self-stamped)

Home: `.claude/env.<hostname>-<machineid8>.local.md`. Gitignore pattern:
`.claude/env.*.local.md`.

* **Learned by doing, not pre-scanned.** The file starts minimal; each time Claude
  attempts a tool it records the outcome. **Negative results are cached too** — "pnpm is
  unavailable, stop trying it" is what actually stops the thrash.
* **Trust window.** Positive facts hold until a command using them fails (then drop and
  re-detect). Negative facts carry a shorter window and are cleared when the user says
  the toolchain changed — otherwise a later-installed tool would never be retried.
* **Self-stamped identity.** The file header stamps the same hostname + machine-id +
  `detected_on`. On read, Claude verifies the stamp matches the live machine; a mismatch
  means the file is foreign (copied in) — ignore it and regenerate. Filename = fast
  lookup; in-file stamp = correctness guarantee for the cloned-VM case where hostname,
  machine-id, and the copied file all coincide.

Example contents:

```
machine: DESKTOP-AB12 · id: 3f9c1a7b · detected_on: 2026-07-10
shell:    powershell    (2026-07-10)
composer: available 2.x (2026-07-10)
npm:      available      (2026-07-10)
pnpm:     unavailable    (2026-07-10)
test:     composer test
serve:    php artisan serve
```

## How to compute the machine id (include verbatim in the conventions file)

Take the OS machine identifier, hash it, keep the first 8 hex chars for the filename;
store the full readable value only if needed. Per OS:

* **Windows (PowerShell):**
  `(Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid`
* **Linux:** `cat /etc/machine-id` (fallback `cat /var/lib/dbus/machine-id`)
* **macOS:** `ioreg -rd1 -c IOPlatformExpertDevice | awk -F'"' '/IOPlatformUUID/{print $4}'`

Hash to the short id (example, Bash): `printf '%s' "<id>" | sha256sum | cut -c1-8`
(PowerShell equivalent via `Get-FileHash`/`[System.Security.Cryptography]`). Detecting
the machine id is itself one cheap probe per session — the one probe that is always
worth running because it names the cache file.

## Out of scope

* **A SessionStart hook** that auto-regenerates the cache (the `update-config` / hooks
  path). Start file-only; add the hook later only if manual refresh proves annoying.
* **Permission-prompt thrash** — already covered by the `fewer-permission-prompts`
  skill; this cache complements it, not replaces it.
* No behavior of the app changes; this is Claude-workflow tooling only.

# Architecture — placement, wiring, and the read/write protocol

No app architecture changes. This describes where the two artifacts sit, how `CLAUDE.md` and
`.gitignore` wire them in, the machine-id computation, and the runtime protocol Claude follows.

## File placement

```
.claude/
  conventions/
    tooling.md                         # Artifact A — checked in
  env.<hostname>-<machineid8>.local.md # Artifact B — gitignored, machine-local
CLAUDE.md                              # + one reference line
.gitignore                             # + one ignore pattern
```

`conventions/` is a new subfolder; the project already keeps Claude tooling under `.claude/`
(`skills/`, `agents/`, `guidelines.md`), so this matches the existing layout rather than
introducing a new top-level location.

## Wiring 1 — the `CLAUDE.md` reference (one line)

Add a single pointer near the top of `CLAUDE.md` (a sensible home is just under the "General"
list, alongside the existing shell/file-tool guidance). Suggested wording:

> * **Toolchain & shell rules are in `.claude/conventions/tooling.md`** — select the shell by
>   tool availability (not OS), never mix one shell's syntax into the other's tool, and consult
>   the machine-local env cache before probing. Read it before running shell commands.

Keep it to one line so the rules stay single-sourced in Artifact A; do not duplicate the rules
into `CLAUDE.md`.

> [!NOTE]
> `CLAUDE.md` currently phrases shell guidance as "PowerShell (primary); Bash tool also
> available." Reconcile that with the platform-neutral rule — either soften the `CLAUDE.md`
> line to point at `tooling.md`, or leave the environment-block description as machine-specific
> fact while the *rule* lives in `tooling.md`. Flagged in `open-questions.md`.

## Wiring 2 — the `.gitignore` pattern (one line)

Append to `.gitignore`:

```
.claude/env.*.local.md
```

The existing `.gitignore` has no `.claude/` entries, so `.claude/` and its committed contents
(`skills/`, `conventions/`, `guidelines.md`) stay tracked; only the env-cache files are ignored.
Place it near the other editor/local-artifact ignores for readability.

## Machine-id computation (goes verbatim into Artifact A)

Take the OS machine identifier, hash it, keep the first 8 hex chars for the filename; keep the
full readable value only if needed.

* **Windows (PowerShell):**
  `(Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid`
* **Linux:** `cat /etc/machine-id` (fallback `cat /var/lib/dbus/machine-id`)
* **macOS:** `ioreg -rd1 -c IOPlatformExpertDevice | awk -F'"' '/IOPlatformUUID/{print $4}'`

Hash to the short id (Bash example): `printf '%s' "<id>" | sha256sum | cut -c1-8`. PowerShell
equivalent via `Get-FileHash` over a stream, or
`[System.Security.Cryptography.SHA256]`. Computing the machine id is one cheap probe per session
— the one probe always worth running, because it names the cache file.

## Runtime protocol (the state machine Claude follows)

```
session needs a tool fact (shell / pkg-mgr / test cmd / binary presence)
        │
        ▼
compute machine-id  ──►  build filename env.<host>-<id8>.local.md
        │
        ▼
file exists? ──no──►  no cache: use portable rules, probe on demand, create cache + stamp
        │yes
        ▼
in-file stamp matches live host+id? ──no──►  FOREIGN (copied in): ignore file, regenerate
        │yes
        ▼
fact present and within trust window? ──yes──►  use it, skip the probe
        │no
        ▼
probe once ──►  append outcome (positive AND negative), dated ──►  use it
        │
        ▼
later: a command using a cached fact fails ──►  drop that fact, re-detect, update cache
```

### Why filename **and** in-file stamp (both, not either)

* **Filename stamp** = O(1) lookup: Claude computes the expected name and checks existence
  without opening foreign files.
* **In-file stamp** = correctness: in a cloned-VM image, the hostname, machine-id, *and* the
  copied cache file can all coincide, so the filename alone would falsely match. The in-file
  `detected_on` + re-derived id verification is the backstop that catches the copy. This is the
  core design decision — do not collapse it to filename-only.

## Component summary

| Concern | Home | Kind |
|---|---|---|
| Portable rules | `.claude/conventions/tooling.md` | checked-in Markdown |
| Machine-local facts | `.claude/env.<host>-<id8>.local.md` | gitignored Markdown |
| Discoverability | one line in `CLAUDE.md` | edit |
| Copy-safety | `.gitignore` pattern + in-file stamp verify | edit + protocol |
| Machine-id recipe | verbatim block in Artifact A | documentation |

No Laravel classes, routes, policies, migrations, or services are involved — the "where logic
lives" rules in `CLAUDE.md` do not apply because there is no application logic here.

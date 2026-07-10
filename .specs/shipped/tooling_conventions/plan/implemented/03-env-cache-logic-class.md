# Task 03 — Testable EnvCache logic class + autoload-dev + unit test

## Scope

The pure, unit-testable core of the hook: everything except the actual context injection and the
SessionStart wiring. This is the task that makes the tricky copy-detection logic correct and
guarded.

**Builds:**
- `.claude/hooks/EnvCache.php` — a plain PHP class (namespaced, e.g. `App\Dev\Tooling\EnvCache`
  or a dedicated `ClaudeTooling\EnvCache` namespace — pick one that does NOT collide with the app
  and is dev-only).
- `autoload-dev` PSR-4 entry in `composer.json` mapping the chosen namespace to `.claude/hooks/`.
- `tests/Unit/EnvCacheTest.php`.

**Does NOT build:** `session-start.php` (task 04), the settings wiring (task 05), or any I/O the
hook performs beyond what the pure methods need.

## Depends on

- Task 02 (the cache file format is fixed, so parsing/stamping targets a settled shape).

## Public surface (design the class around testability)

Pure/near-pure methods, each independently assertable against fixture strings — no session races:

- `machineId(): string` — derive the OS machine id and return the 8-hex short id. Per OS:
  Windows registry `MachineGuid`, Linux `/etc/machine-id` (fallback `/var/lib/dbus/machine-id`),
  macOS `ioreg`. Hash → first 8 hex. **Fallback:** if the source is unavailable, hash `hostname`
  and mark the result so `stampLine()` can append `(hostname-fallback)`. Never throw — return a
  usable id or a clearly-marked fallback.
- `hostname(): string`.
- `cacheFilename(): string` — `env.<host>-<id8>.local.md`.
- `stampLine(): string` — `machine: <host> · id: <id8> · detected_on: <today>`.
- `parseStamp(string $contents): ?array` — extract `machine`/`id`/`detected_on` from a file's
  header, or null if malformed/absent.
- `matchesLiveMachine(string $contents): bool` — true iff the parsed stamp's host+id equal the
  live machine's. This is the copy/clone detector.
- `foreignFiles(string $claudeDir): array` — list `env.*.local.md` paths whose stamp does **not**
  match the live machine (the prune candidates).

Keep filesystem-touching helpers thin and separate from the pure string logic so the unit test
can feed fixtures directly to `parseStamp` / `matchesLiveMachine`.

## Key decisions already made

- Copy-safety = filename + in-file stamp (binding decision 6); identical-clone accepted (11).
- Hostname-fallback for machine id (Q2 / overview acceptance).
- Dev-only autoload — hook tooling must not enter the shipped `autoload` (binding decision 12).

## `tests/Unit/EnvCacheTest.php` — cases

Style: plain `PHPUnit\Framework\TestCase`, no DB (mirror `SpecsStatusConsistencyTest`).

- `parseStamp` returns the fields for a well-formed header.
- `parseStamp` returns null for a header with no stamp / malformed / missing.
- `matchesLiveMachine` is **false** for a fixture stamped with a different host+id (the copied
  file case) and **true** for one stamped with the live host+id.
- `foreignFiles` selects exactly the mismatched fixtures from a temp dir and excludes the matching
  one.
- `cacheFilename` / `stampLine` embed the same `<id8>` (filename ↔ in-file stamp agree).
- `machineId` returns a non-empty 8-char hex under normal conditions (may be an integration-ish
  assertion; keep it tolerant of the CI environment).

## Docs to consult

- `expanded/architecture.md` (machine-id recipe, the read/verify state machine, why filename+stamp)
- `expanded/artifacts.md` (Artifact B header/body)
- `expanded/testing.md` (which cases are automatable vs. manual)

## Tests this task adds

`tests/Unit/EnvCacheTest.php` (above). `composer test` must pass. Update `CHANGELOG.md` under
`Added`.

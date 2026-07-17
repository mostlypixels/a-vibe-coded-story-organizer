---
status: draft
---

# Multiple Database Engines

## Problem

The app is meant to be installable on several database engines — `sqlite`, `mysql`,
`mariadb`, `pgsql`, and `sqlsrv` are all configured in `config/database.php`, and code
is deliberately written to be portable (Blueprint migrations with no engine-specific
DDL; `ProjectSearch` uses portable `LIKE '%…%'` with an `ESCAPE` clause rather than
FULLTEXT/FTS).

But that portability is currently an **unverified claim**: the test suite runs only on
in-memory SQLite (`phpunit.xml`, `RefreshDatabase`, parallel paratest). Nothing exercises
the app against MySQL, MariaDB, Postgres, or SQL Server, so a genuine cross-engine
regression would ship undetected. Engine behavior does differ in ways that bite:

- **`LIKE` case-sensitivity differs.** SQLite and MySQL default to case-insensitive
  `LIKE`; **PostgreSQL `LIKE` is case-sensitive**. So a query that reads as
  case-insensitive on the default install is silently case-sensitive on Postgres today.
- **`_ci` collations fold accents.** MySQL's configured `utf8mb4_unicode_ci` (and SQL
  Server CI collations) fold accents *and* case at comparison time; SQLite folds neither
  beyond ASCII case. Relying on the engine's collation for correctness therefore produces
  **different results per engine**.
- **`lower()` locale behavior** and other small semantic differences vary by engine.

The `search-accent-folder` spec is the immediate motivating example: it must be correct
on every engine and must not depend on any one engine's collation.

## Goals

- Make "installable on several database engines" a **verified** property, not just a
  coding convention — the whole suite runs green against each supported engine in CI.
- Establish and document the project rule that keeps this true going forward.
- Document which engines are supported and any per-engine setup they need.

## Non-goals

- Changing the **default** developer/test experience: local and default-CI runs stay on
  fast in-memory SQLite (parallel paratest). The matrix is *additional* coverage.
- Rewriting existing queries. This spec is about verification, documentation, and a
  project rule — individual portability bugs it surfaces are fixed under their own specs
  (the accent case is `search-accent-folder`).

## Rough approach

**1. Project rule — correctness in application code, never in a collation.** Behavior a
feature depends on (case-insensitive matching, accent folding, ordering) must be produced
in application code so it is identical on every engine, rather than delegated to a
per-engine collation. `search-accent-folder`'s `AccentFolder` is the reference pattern.

**2. CI test matrix (the core deliverable).** Add a CI job matrix that runs the full
`composer test` suite against live **MySQL, MariaDB, PostgreSQL, and SQL Server** (service
containers), in addition to the existing fast SQLite job. This is the only thing that
actually guarantees the portability claim over time, and it protects *every* query in the
app, not just search. Expected friction to account for: a live DB per matrix leg, slower
runs than SQLite, per-process database orchestration for paratest (or reduced/serial
parallelism on the matrix legs), and CI credentials / wait-for-ready handling.

**3. Supported-engines documentation.** A page under `documentation/` (and an
install/README note) listing the supported engines, the recommended charset/collation for
the server engines (e.g. `utf8mb4` / `utf8mb4_unicode_ci` for MySQL, already the config
default), and the project rule from step 1. Reference it from
`documentation/architecture.md`.

## Testing

The deliverable *is* test infrastructure: success = the existing suite passing unchanged
on each engine in the matrix. No new application behavior is added here, so no new feature
tests are required beyond what the matrix runs; any failures it exposes are triaged and
fixed under the relevant feature's spec.

## Related

- `search-accent-folder` — the first feature whose cross-engine correctness this matrix
  verifies. It ended up matching in PHP (not SQL) precisely because a folding SQL expression
  was *not* portable — a nested-`REPLACE` chain overflowed SQLite's parser on CI. This matrix
  is what would have caught that before merge, and what proves search stays identical per driver.

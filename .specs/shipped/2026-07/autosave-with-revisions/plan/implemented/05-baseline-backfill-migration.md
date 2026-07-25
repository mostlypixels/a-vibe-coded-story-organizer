# Task 5 — Baseline backfill migration

## Scope

One migration (`2026_07_XX_000002_backfill_baseline_revisions.php`) that walks every
existing row of every registered model (`Project`, `Act`, `Chapter`, `Plotline`,
`Event`, `Scene`, `CodexEntry`) and calls `RevisionRecorder::ensureBaseline()` for each
of that model's registered fields (from `AutosavableFields::REGISTRY`).

Does **not** include any new write logic — this task is purely "drive the existing
`ensureBaseline()` over existing data," proving task 4's "identical code path for live
writes and backfill" requirement (`handoff.md` §9.2) actually holds.

## Depends on

Task 3 (`AutosavableFields::REGISTRY`), task 4 (`RevisionRecorder::ensureBaseline()`).

## Key decisions already made

* **Must batch** (`chunkById` or equivalent) — this touches every row of every
  registered model in an existing install; a naive `all()` risks memory exhaustion on
  a large project set.
* **Idempotent** — safe to re-run (matches `ensureBaseline()`'s own no-op-if-already-
  seeded behavior), so a partial run followed by a retry does not double-seed.
* **Populates `size_bytes`** on every backfilled row (already guaranteed by task 4's
  `ensureBaseline()` implementation — this task just needs to not bypass it).
* This is a **data** migration, not a schema migration — no `up()`/`down()` schema
  change; `down()` can reasonably be a no-op (or delete only `origin: baseline` rows —
  decide during implementation, document the choice in the migration's docblock either
  way).

## Consult

* `expanded/data-model.md` — "Baseline seeding + backfill" section, specifically "The
  backfill migration for existing rows uses the identical code path."
* `handoff.md` §2.6, §9.2 — why the baseline exists and why it must predate any live
  autosave for the feature to be safe on day one.

## Tests

* Seed (via factories) one row per registered model with non-empty values in every
  registered field, run the migration, assert exactly one `baseline` revision per
  `(entity, field)` exists, each with `created_at` equal to that row's `updated_at` and
  `size_bytes` populated correctly.
* A field left null/empty on a seeded row gets **no** baseline row (matches
  `ensureBaseline()`'s no-op rule).
* Running the migration twice does not create duplicate baseline rows.
* A model instance that already has a revision for a field (simulate by inserting one
  directly before running the migration) is skipped for that field — the migration
  never overwrites pre-existing history.

# Task 11 — Snapshots and entity-level comparison

## Scope

`App\Support\EntitySnapshot` (readonly): `array<string, ?Revision> $fields` + the
`SavePoint` it was resolved from.
`App\Support\FieldComparison` (readonly): `field`, `FieldKind $kind`, `?Revision $from`,
`?Revision $to`, `RevisionDiffResult $result`.

`App\Services\RevisionSnapshot`:

* `asOf(Model $entity, SavePoint $point): EntitySnapshot` — **for every registered field**,
  the newest revision at or before that save point's timestamp. Tie-break by id:
  `where(created_at < :t)->orWhere(created_at = :t AND id <= :lastId)`, ordered
  `created_at desc, id desc`, `limit 1`, one small query per field (at most six per
  entity, bounded by the registry);
* `current(Model $entity): EntitySnapshot` — the live state.

`App\Services\RevisionComparison::between(Model $entity, SavePoint $from, SavePoint $to, ?string $field): Collection<FieldComparison>`:

* resolves both snapshots;
* skips any field whose two sides resolve to the same revision id — unchanged fields are
  never diffed and never rendered (the caller gets their names for the "N fields
  unchanged" line);
* otherwise builds a `FieldComparison` via `RevisionDiffer` (task 7), hydrating `value`
  only for the fields it actually diffs;
* honours `$field` as a filter without changing which pair is compared.

No routes, no views — task 14 renders this.

## Depends on

Tasks 7, 10.

## Key decisions already made

* **A save point is a moment, not a set of values.** A save that touched only `notes`
  still implies a state for every other field. This is what makes entity-level compare
  truthful — and it means a field neither save wrote can appear as changed. Binding
  decision 5: correct, not a bug.
* Hydrating `value` is confined to the fields being diffed; the snapshot resolution itself
  selects ids and timestamps.
* Chronological order is enforced by the caller (task 14) before this service runs; the
  comparison never reverses a pair itself.

## Consult

* `expanded/architecture.md` — *Snapshots*, `RevisionSnapshot`, `RevisionComparison`.
* `expanded/overview.md` — the compare acceptance criteria.

## Tests

`tests/Unit/RevisionSnapshotTest.php` and
`tests/Unit/Services/RevisionComparisonTest.php`:

* `asOf()` resolves each field to the newest revision at or before the moment, **including
  fields the save did not write**;
* ties inside one second resolve by id, deterministically;
* a field with no revision at that point resolves to null;
* `current()` matches the entity's live column values;
* `between()` skips fields whose two sides are the same revision and reports them as
  unchanged;
* a field changed by an unrelated save *between* the two points appears as changed;
* a field that did not exist at the older point yields `from = null` (whole-value insert);
* `$field` filtering returns exactly one comparison;
* the number of diffs computed equals the number of changed fields (assert the differ is
  not called for unchanged ones).

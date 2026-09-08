# Data model

**No migration.** `revisions` already stores an arbitrary string per `(entity, field)`
grouped by `save_id`. `name` is a string; a set is stored as its canonical string form. The
table learns nothing about relations.

## Canonical set form

Sorted, newline-joined, no trailing newline. Sorting is what makes the stored value
independent of insertion order, so reordering aliases alone records nothing.

- Sort with a stable, locale-independent comparison. `strcmp`, not `Collator` — the stored
  value is a storage key, not a display list, and a locale-sensitive sort would make the
  same set hash differently on different machines.
- An empty set is the empty string, distinct from `null` (never scanned). `RevisionRecorder`
  already treats `''` and `null` differently; keep that.
- The value is never rendered raw. The compare screen splits it back into members.

## Sizes and pruning

- `revisions.size_bytes` is already written per row. A set value is tens of bytes; a name is
  smaller still. Nothing to re-tune.
- `config('revisions.caps')` is keyed `"slug.field"` with a `default` fallback, so `name`
  and the set fields resolve to the default with no config change. **They do not need a cap
  entry** — they are not submitted through the autosave endpoint, so `validationRule()` never
  runs for them. Adding one anyway would imply an endpoint that does not exist.
- `config('revisions.windows')` likewise. A tracked-only field never coalesces: every manual
  save is its own row.

> [!WARNING]
> Tracked-only rows are `origin: Manual` and therefore **never prune** — `Revision::
> prunable()` selects `Automatic` only. A writer who renames a character fifty times keeps
> fifty rows for ever. That is consistent with how manual saves already behave, but it is a
> new growth path on a hot table. See [open-questions.md](open-questions.md).

## Indexes

`revisions_entity_field_idx` is `(revisionable_type, revisionable_id, field, created_at)` —
already the right shape for "this entity's history of this field". Adding fields adds rows,
not query shapes. No index change.

## Seeding

`MelusineSeeder` creates entries with names, aliases and tags but writes no revisions, so
seeded entries keep an empty history — the same as today for descriptions. Nothing to
change; the "no history yet" empty state is the correct render.

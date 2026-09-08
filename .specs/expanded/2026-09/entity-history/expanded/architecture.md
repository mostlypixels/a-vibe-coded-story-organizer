# Architecture

## Separating "tracked" from "autosaved"

The load-bearing change. Today one registry answers both questions, so a field cannot be
remembered without also gaining an autosave endpoint, a coalescing window and a character
cap.

**Do not widen `AutosavableFields::REGISTRY`.** Its entries drive
`routes/web.php`'s autosave endpoint, `validationRule()`, `windowSeconds()` and
`characterCap()`. A `name` entry there would give `name` an autosave route it must not have
(criterion 6).

**New: `App\Support\TrackedFields`** — the registry of fields that have history but do not
autosave, plus the union door the revision layer reads:

```php
// Fields tracked but never autosaved, by the same slug AutosavableFields uses.
public const REGISTRY = [
    'codex' => ['name' => FieldKind::Plain, 'aliases' => FieldKind::Set, 'tags' => FieldKind::Set],
    'scene' => ['name' => FieldKind::Plain],
    // …one per slug in AutosavableFields::REGISTRY
];

/** Every field with history for a model: autosaved ∪ tracked. */
public static function forModel(string $modelClass): array;   // ['field' => FieldKind]

/** True when the field is tracked but has no autosave endpoint. */
public static function isTrackedOnly(string $modelClass, string $field): bool;
```

Then **every revision-layer caller switches from `AutosavableFields::fieldsForModel()` to
`TrackedFields::forModel()`**, and the autosave layer keeps calling `AutosavableFields`
untouched. That split is the whole design; the rest follows.

Callers to move (find them all — a missed one silently keeps a field out of history):
`RevisionHistory`, `RevisionRecorder`, `RevisionReverter`, `RevisionComparison`,
`RevisionBrowserController`, `ProjectRevisionsBrowser`, `RevisionPurger` and the
`revisions/index` field selector.

> [!WARNING]
> `AutosavableFields::resolveField()` is the autosave endpoint's 404 gate. It must keep
> reading `AutosavableFields` only. Pointing it at the union is exactly the bug criterion 6
> guards against.

## `FieldKind::Set`

New case, for a value that is a set of short strings rather than a document. It selects the
summarizer strategy and the comparison renderer; it needs no validation rule, because a set
field is never submitted through the autosave endpoint.

## Recording a set

`aliases` is a `HasMany` (`CodexAlias.alias`); `tags` is a `BelongsToMany`. Neither is a
column, but `revisions.value` is a string, and the table must not learn about relations.

**Store the canonical set as the value:** members sorted, newline-joined. Sorting makes the
stored value independent of insertion order, so reordering alone never records a change.

`RevisionRecorder` needs no new storage path — it already writes an arbitrary string per
field. What changes is *who reads the value off the model*: a column read (`getAttribute`)
does not work for a relation.

**New: `App\Support\FieldValueReader`** — one static that returns a field's current value as
the string a revision stores, dispatching on `FieldKind`. Every snapshot/compare site calls
it instead of `getAttribute()`.

## Recording the change

`RecordsManualRevisions` already owns the snapshot-then-record dance in all seven entity
controllers. It keeps owning it; `snapshotAutosaved()` widens to snapshot **tracked** fields
too (rename it — `snapshotTracked()` — since the name is now wrong).

**Ordering trap.** `CodexEntryController::update()` saves the model, then syncs aliases and
tags. A snapshot taken before the update is correct, but `recordManualSave()` must run
**after** the relation syncs, or every set change records as unchanged. Today's call sites
put it right after `$model->update()`. Check each one.

## Folding four rows into one save point

Two causes, both in the read layer — no stored row changes.

1. **Baselines are shown as peers of the save.** `RevisionRecorder::ensureBaseline()` writes
   one `Baseline` row per field before the first edit, and `RevisionHistory::foldGroups()`
   makes each its own save point. A baseline belongs to the save that follows it, not to a
   moment of its own. Only the genuine first-ever baseline stays a row, and it already has
   its own dedicated rendering ("Baseline — value before revision history").
2. **A save point takes its label from a row, not from the save.** One `save_id` can hold a
   `Manual` row and an `Automatic` row when a manual save closes an open autosave window.

**Rank the origins and label the save point by the strongest present:**
`Revert > Manual > Automatic > Baseline`. One save point, one badge.

`save_id` already groups per-field rows into a save point — the grouping is right, only its
presentation is wrong. Do not change how `save_id` is assigned.

## Summaries in words

`RevisionSummarizer` computes `summary_html` and `change_count` once at write time, so no
list page diffs at read time. Keep that; add a strategy per `FieldKind`:

| Kind | Summary |
| --- | --- |
| Rich / Markdown / Plain (long) | today's excerpt-based summary, unchanged |
| Plain, short (`name`) | "renamed Bertrand to Bertran" |
| Set | "added alias Tisi", "removed tag draft", "added 2 aliases, removed 1" |

`change_count` for a set is the number of members added plus removed — it already drives the
list page's count, so it must stay a count of changes, not of members.

A rename needs both old and new values; `RevisionSummarizer` already receives
`$previousValue` for the diff. Nothing new is needed to reach it.

## Authorization

Unchanged. History routes already authorize through the owning project; `Revision` carries a
real `project_id` for exactly that. No new route, no new policy.

## Conflicts with existing invariants

- **Codex attribute values must not gain edit-time history.** `AutosavableFields`' docblock
  states it. `TrackedFields` must not register `CodexAttributeValue`, and the union door
  must not walk relations looking for trackables.
- **Pruning.** `Revision::prunable()` selects `origin: Automatic` only. A tracked-only field
  is never autosaved, so its rows are `Manual` and survive retention — correct, and worth an
  explicit test, because it means renames accumulate without bound.
- **`$timestamps = false` on `Revision`.** Writers set `created_at` explicitly. A new write
  path that forgets this gets a null timestamp and sorts wrong.

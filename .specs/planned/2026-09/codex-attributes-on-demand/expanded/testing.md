# Testing

Extend `tests/Feature/CodexAttributeSheetsTest.php` and `CodexEntryTest.php`; add
`tests/Feature/CodexEntryAttributeTest.php` for the new routes.

## `CodexAttributeSheets`

- `attached()` keeps a pair whose only row is `''`; drops one with no rows.
- `setOnly()` drops a pair whose only row is `''` — **the regression test for the current
  defect**; it fails before the change.
- `setOnly()` keeps a pair whose Start is `''` but a later period is filled.
- `unattachedFor()` excludes attached ids and attributes of another entry type.

## Create

- Posting two `attribute_baselines` on a project with five attributes writes two rows,
  not five (fails before the change).
- Posting a blank baseline writes no row.
- Create form response contains no attribute value input.
- Existing `withValidator` cases still reject a foreign-project and wrong-type id.

## Attach / detach

- Attach creates one Start-anchored `''` row; the block then renders on edit.
- Attach is idempotent — a second post makes no duplicate (`ensureBaseline` guarantees it).
- Attach of another project's attribute → 404.
- Attach of an attribute that does not apply to the type → validation error.
- Detach deletes all of that pair's rows, including mid-timeline periods, and leaves the
  `codex_attributes` row and other entries' values alone.
- Non-owner gets 403 on both.

## Interaction with existing rules

- Removing the last period via `codex.attribute-values.destroy` leaves the pair with no
  rows, so the edit form no longer shows it — assert on the response, not just the DB.
- The Start-baseline guard still returns 403 when other periods exist.
- `CodexAsOfResolver` output is unchanged for a pair whose rows are all blank (it already
  filtered them) — an assertion that the two predicates now agree.

## Migration

A test project with an all-blank pair and a project with a blank-then-filled pair; run
the migration; the first pair is gone, the second intact.
